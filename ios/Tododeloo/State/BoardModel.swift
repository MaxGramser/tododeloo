import Observation
import SwiftUI

enum BoardContext: Hashable {
    case today
    case master
    case custom(id: Int)
}

/// Backs any screen that shows a single collection of todos (Today, Alles, or a
/// custom list). Owns loading, the quick-add field, and every mutation that the
/// row tap and the press-and-hold context menu can trigger.
@MainActor
@Observable
final class BoardModel {
    let context: BoardContext

    var listId: Int?
    var listType: String = ""
    var listName: String = ""
    var dateString: String = ""
    var todos: [Todo] = []
    var sortMode: SortMode = .manual

    // Custom lists available as "add to list" targets (lazily fetched).
    var customLists: [ListSummary] = []

    // View-only filters applied client-side over the loaded todos.
    var priorityFilter: Priority?
    var tagFilter: Int?

    // Which day the Today board is showing ("" == today). Lets the user page
    // back and forth through daily lists without leaving the tab.
    var viewingDate: String = ""

    // Last todo deleted from this board, kept briefly so it can be restored.
    var recentlyDeleted: Todo?

    // Today's morning ritual.
    var needsRitual = false
    var previousWorkday = ""
    var carryOverCandidates: [Todo] = []
    var earlierCandidates: [Todo] = []
    var masterOpenTodos: [Todo] = []
    var preScheduled: [Todo] = []

    var isLoading = false
    var hasLoaded = false
    var errorMessage: String?
    var quickAddText = ""

    private let api = APIClient.shared
    var onUnauthorized: (() -> Void)?

    init(context: BoardContext) {
        self.context = context
    }

    var canRemoveFromList: Bool {
        listId != nil && listType != "master"
    }

    var title: String {
        switch context {
        case .today: return isViewingToday ? "Vandaag" : DateText.medium(effectiveDate)
        case .master: return "Alles"
        case .custom: return listName.isEmpty ? "Lijst" : listName
        }
    }

    var openCount: Int { todos.filter { !$0.isCompleted }.count }

    // MARK: - Day navigation (Today board)

    private var todayISO: String { RecurrencePresetOption.dayFormatter.string(from: Date()) }
    var effectiveDate: String { viewingDate.isEmpty ? todayISO : viewingDate }
    var isViewingToday: Bool { effectiveDate == todayISO }

    func shiftDay(by days: Int) async {
        guard let date = DateText.parse(effectiveDate) else { return }
        let next = Calendar(identifier: .gregorian).date(byAdding: .day, value: days, to: date) ?? date
        viewingDate = DateText.ymd(next)
        await load()
    }

    func goToToday() async {
        viewingDate = ""
        await load()
    }

    // MARK: - Filtering (view-only)

    var visibleTodos: [Todo] {
        todos.filter { todo in
            (priorityFilter == nil || todo.priorityValue == priorityFilter)
                && (tagFilter == nil || (todo.tags ?? []).contains { $0.id == tagFilter })
        }
    }

    var hasActiveFilter: Bool { priorityFilter != nil || tagFilter != nil }

    /// Distinct tags present on the loaded todos, for the filter menu.
    var availableTags: [Tag] {
        var seen = Set<Int>()
        var result: [Tag] = []
        for todo in todos {
            for tag in todo.tags ?? [] where !seen.contains(tag.id) {
                seen.insert(tag.id)
                result.append(tag)
            }
        }
        return result.sorted { $0.name.lowercased() < $1.name.lowercased() }
    }

    func clearFilters() {
        priorityFilter = nil
        tagFilter = nil
    }

    // MARK: - Loading

    func load() async {
        isLoading = true
        defer { isLoading = false; hasLoaded = true }
        do {
            switch context {
            case .today:
                let response = isViewingToday ? try await api.today() : try await api.day(effectiveDate)
                dateString = response.date
                needsRitual = response.needsRitual
                if response.needsRitual {
                    previousWorkday = response.previousWorkday
                    carryOverCandidates = response.carryOverCandidates
                    earlierCandidates = response.earlierCandidates
                    masterOpenTodos = response.masterOpenTodos
                    preScheduled = response.preScheduled
                    listId = response.list?.id
                    todos = []
                } else if let list = response.list {
                    apply(list)
                } else {
                    // No daily list exists for this date yet — show an empty
                    // board instead of leaving the previous day's todos behind.
                    listId = nil
                    listType = "daily"
                    listName = ""
                    sortMode = .manual
                    todos = []
                }
            case .master:
                apply(try await api.master())
            case .custom(let id):
                apply(try await api.list(id))
            }
            customLists = (try? await api.lists())?.filter { !$0.isMaster } ?? customLists
            errorMessage = nil
        } catch {
            handle(error)
        }
    }

    private func apply(_ list: TodoList?) {
        guard let list else { return }
        listId = list.id
        listType = list.type
        listName = list.displayName
        dateString = list.date ?? dateString
        sortMode = SortMode(rawValue: list.sortMode) ?? .manual
        todos = sorted(list.todos ?? [])
    }

    private func sorted(_ items: [Todo]) -> [Todo] {
        items.enumerated()
            .sorted { lhs, rhs in
                if lhs.element.isCompleted != rhs.element.isCompleted {
                    return !lhs.element.isCompleted
                }
                return lhs.offset < rhs.offset
            }
            .map(\.element)
    }

    // MARK: - Quick add

    func submitQuickAdd() async {
        let title = quickAddText.trimmingCharacters(in: .whitespacesAndNewlines)
        guard !title.isEmpty else { return }
        quickAddText = ""

        // Show it instantly; the reload below reconciles with the server's row.
        let draft = Todo.draft(title: title)
        todos.insert(draft, at: 0)

        do {
            // Always go through quick-add so the Dutch date/recurrence parsing
            // runs. Passing the current list makes a date-less todo land on the
            // page you're on (today / master / this custom list); an explicit
            // date or recurrence in the text still wins.
            _ = try await api.quickAdd(title: title, listId: listId)
            await load()
        } catch {
            todos.removeAll { $0.id == draft.id }
            handle(error)
        }
    }

    // MARK: - Morning ritual

    func startDay(carryOverIds: [Int], newTitles: [String]) async {
        do {
            _ = try await api.startDay(dateString, carryOverIds: carryOverIds, newTitles: newTitles)
            await load()
        } catch {
            handle(error)
        }
    }

    // MARK: - Todo actions

    func toggle(_ todo: Todo) async {
        let nowCompleted = !todo.isCompleted
        if let index = todos.firstIndex(where: { $0.id == todo.id }) {
            todos[index].completedAt = nowCompleted ? Date() : nil
            todos = sorted(todos)
        }
        do {
            let updated = nowCompleted ? try await api.complete(todo.id) : try await api.uncomplete(todo.id)
            replace(updated)
        } catch {
            handle(error)
            await load()
        }
    }

    func delete(_ todo: Todo) async {
        todos.removeAll { $0.id == todo.id }
        recentlyDeleted = todo
        do {
            try await api.deleteTodo(todo.id)
        } catch {
            recentlyDeleted = nil
            handle(error)
            await load()
        }
    }

    func undoDelete() async {
        guard let todo = recentlyDeleted else { return }
        recentlyDeleted = nil
        do {
            _ = try await api.restore(todo.id)
            await load()
        } catch {
            handle(error)
        }
    }

    func dismissUndo() {
        recentlyDeleted = nil
    }

    // MARK: - Lists & ordering

    var addableLists: [ListSummary] {
        customLists.filter { $0.id != listId }
    }

    func addToList(_ todo: Todo, listId: Int) async {
        do {
            _ = try await api.addToList(todo.id, listId: listId)
            await load()
        } catch {
            handle(error)
        }
    }

    func setSortMode(_ mode: SortMode) async {
        guard let listId else { return }
        sortMode = mode
        let visibleIds = mode == .manual ? todos.map(\.id) : nil
        do {
            apply(try await api.setSortMode(listId: listId, mode: mode.rawValue, visibleTodoIds: visibleIds))
        } catch {
            handle(error)
        }
    }

    /// Persist a manual reorder. The given ids are the active todos in their new
    /// order; completed ones keep their place at the end.
    func persistOrder(activeOrderedIds: [Int]) async {
        guard let listId else { return }
        let completedIds = todos.filter(\.isCompleted).map(\.id)
        do {
            apply(try await api.reorder(listId: listId, todoIds: activeOrderedIds + completedIds))
        } catch {
            handle(error)
        }
    }

    func duplicate(_ todo: Todo) async {
        do {
            _ = try await api.duplicate(todo.id, listId: contextListId)
            await load()
        } catch {
            handle(error)
        }
    }

    func addToToday(_ todo: Todo) async {
        do {
            _ = try await api.addToToday(todo.id)
            await load()
        } catch {
            handle(error)
        }
    }

    func move(_ todo: Todo, toDate date: String) async {
        do {
            let fromListId = (listType == "daily" || listType == "custom") ? listId : nil
            _ = try await api.moveToDate(todo.id, date: date, fromListId: fromListId)
            await load()
        } catch {
            handle(error)
        }
    }

    func rename(_ todo: Todo, title: String) async {
        let trimmed = title.trimmingCharacters(in: .whitespacesAndNewlines)
        guard !trimmed.isEmpty else { return }
        if let index = todos.firstIndex(where: { $0.id == todo.id }) {
            todos[index].title = trimmed
        }
        do {
            replace(try await api.updateTodo(todo.id, title: trimmed))
        } catch {
            handle(error)
            await load()
        }
    }

    func setPriority(_ todo: Todo, _ priority: Priority) async {
        if let index = todos.firstIndex(where: { $0.id == todo.id }) {
            todos[index].priority = priority.rawValue
            todos = sorted(todos)
        }
        do {
            replace(try await api.updateTodo(todo.id, priority: priority.rawValue))
        } catch {
            handle(error)
            await load()
        }
    }

    func removeFromList(_ todo: Todo) async {
        guard let listId else { return }
        todos.removeAll { $0.id == todo.id }
        do {
            _ = try await api.removeFromList(todo.id, listId: listId)
        } catch {
            handle(error)
            await load()
        }
    }

    func addSubTodo(_ todo: Todo, title: String) async {
        let trimmed = title.trimmingCharacters(in: .whitespacesAndNewlines)
        guard !trimmed.isEmpty else { return }
        do {
            replace(try await api.createSubTodo(todoId: todo.id, title: trimmed))
        } catch {
            handle(error)
        }
    }

    // MARK: - Recurrence

    /// Anchor a new recurrence on the day being viewed; today for non-daily lists.
    var recurrenceAnchorISO: String {
        if listType == "daily", !dateString.isEmpty {
            return dateString
        }
        return RecurrencePresetOption.dayFormatter.string(from: Date())
    }

    var recurrencePresets: [RecurrencePresetOption] {
        RecurrencePresetOption.presets(anchorISO: recurrenceAnchorISO)
    }

    func setRecurrence(_ todo: Todo, preset: String) async {
        do {
            replace(try await api.setRecurrence(todo.id, preset: preset, anchorDate: recurrenceAnchorISO))
        } catch {
            handle(error)
        }
    }

    func setCustomRecurrence(_ todo: Todo, rrule: String) async {
        do {
            replace(try await api.setRecurrence(todo.id, rrule: rrule, anchorDate: recurrenceAnchorISO))
        } catch {
            handle(error)
        }
    }

    func stopRecurrence(_ todo: Todo) async {
        guard let recurrenceId = todo.recurrenceId else { return }
        do {
            try await api.stopRecurrence(recurrenceId)
            await load()
        } catch {
            handle(error)
        }
    }

    // MARK: - Helpers

    private var contextListId: Int? {
        if case .custom(let id) = context { return id }
        return nil
    }

    private func replace(_ todo: Todo) {
        if let index = todos.firstIndex(where: { $0.id == todo.id }) {
            todos[index] = todo
            todos = sorted(todos)
        }
    }

    private func handle(_ error: Error) {
        if case APIError.unauthorized = error {
            onUnauthorized?()
            return
        }
        errorMessage = (error as? APIError)?.errorDescription ?? error.localizedDescription
    }
}

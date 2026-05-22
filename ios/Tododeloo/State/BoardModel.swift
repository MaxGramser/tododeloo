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

    // Today's morning ritual.
    var needsRitual = false
    var previousWorkday = ""
    var carryOverCandidates: [Todo] = []
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
        case .today: return "Vandaag"
        case .master: return "Alles"
        case .custom: return listName.isEmpty ? "Lijst" : listName
        }
    }

    var openCount: Int { todos.filter { !$0.isCompleted }.count }

    // MARK: - Loading

    func load() async {
        isLoading = true
        defer { isLoading = false; hasLoaded = true }
        do {
            switch context {
            case .today:
                let response = try await api.today()
                dateString = response.date
                needsRitual = response.needsRitual
                if response.needsRitual {
                    previousWorkday = response.previousWorkday
                    carryOverCandidates = response.carryOverCandidates
                    masterOpenTodos = response.masterOpenTodos
                    preScheduled = response.preScheduled
                    listId = response.list?.id
                    todos = []
                } else {
                    apply(response.list)
                }
            case .master:
                apply(try await api.master())
            case .custom(let id):
                apply(try await api.list(id))
            }
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
        do {
            switch context {
            case .today:
                _ = try await api.quickAdd(title: title)
            case .master:
                _ = try await api.createTodo(title: title)
            case .custom(let id):
                _ = try await api.createTodo(title: title, listId: id)
            }
            await load()
        } catch {
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
        do {
            try await api.deleteTodo(todo.id)
        } catch {
            handle(error)
            await load()
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
        do {
            replace(try await api.updateTodo(todo.id, title: trimmed))
        } catch {
            handle(error)
        }
    }

    func setPriority(_ todo: Todo, _ priority: Priority) async {
        do {
            replace(try await api.updateTodo(todo.id, priority: priority.rawValue))
        } catch {
            handle(error)
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

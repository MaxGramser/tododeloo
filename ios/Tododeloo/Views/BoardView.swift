import SwiftUI

/// Renders one board (Today / Alles / a custom list): editorial header, a fast
/// quick-add field, and the todo rows with their context menus and sheets.
struct BoardView: View {
    @Environment(Session.self) private var session
    @Environment(\.scenePhase) private var scenePhase
    @Bindable var model: BoardModel
    var showsHeader = true

    @State private var sheet: TodoSheet?
    @State private var reordering = false
    @FocusState private var quickAddFocused: Bool

    var body: some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 0) {
                if showsHeader {
                    header
                }
                if model.isDayBoard {
                    dayNav
                }
                quickAddField

                if let message = model.errorMessage {
                    errorBanner(message)
                }

                content
                    // Reorder when a todo gets completed, fade on add/remove —
                    // the standard SwiftUI list-diff animation.
                    .animation(.snappy(duration: 0.28), value: model.visibleTodos)
            }
            .padding(.bottom, 40)
        }
        .background(Theme.background.ignoresSafeArea())
        .scrollDismissesKeyboard(.interactively)
        .refreshable { await model.load() }
        .toolbar { boardToolbar }
        .overlay(alignment: .bottom) {
            if let deleted = model.recentlyDeleted {
                undoBanner(deleted)
            }
        }
        .sheet(item: $sheet) { route in
            sheetView(for: route)
        }
        .sheet(isPresented: $reordering) {
            ReorderSheet(items: model.todos.filter { !$0.isCompleted }) { orderedIds in
                Task { await model.persistOrder(activeOrderedIds: orderedIds) }
            }
        }
        .task {
            model.onUnauthorized = { session.handleUnauthorized() }
            if !model.hasLoaded {
                await model.load()
            }
        }
        // Coming back from the background: the iOS widget, web, or Mac app may
        // have changed things while we were away, so re-fetch the board.
        .onChange(of: scenePhase) { _, phase in
            if phase == .active, model.hasLoaded {
                Task { await model.load() }
            }
        }
        .task(id: model.recentlyDeleted?.id) {
            guard model.recentlyDeleted != nil else { return }
            try? await Task.sleep(for: .seconds(5))
            model.dismissUndo()
        }
    }

    // MARK: - Content

    @ViewBuilder
    private var content: some View {
        if model.todos.isEmpty && model.hasLoaded {
            EmptyStateView(
                headline: "Niets hier.",
                subtitle: "Typ hierboven om iets toe te voegen."
            )
        } else if model.visibleTodos.isEmpty && model.hasActiveFilter {
            EmptyStateView(
                headline: "Geen resultaten.",
                subtitle: "Geen taken voldoen aan dit filter."
            )
        } else {
            ForEach(model.visibleTodos) { todo in
                row(for: todo)
                Rectangle()
                    .fill(Theme.hairline)
                    .frame(height: 1)
                    .padding(.leading, 20)
            }
        }
    }

    // MARK: - Header

    private var header: some View {
        VStack(alignment: .leading, spacing: 6) {
            MonoLabel(subtitle)
            HStack(alignment: .firstTextBaseline, spacing: 8) {
                Text(model.title)
                    .font(.display(40))
                    .foregroundStyle(Theme.ink)
                AccentDot(size: 9)
            }
        }
        .frame(maxWidth: .infinity, alignment: .leading)
        .padding(.horizontal, 20)
        .padding(.top, 12)
        .padding(.bottom, 8)
    }

    private var subtitle: String {
        switch model.context {
        case .today:
            return model.isViewingToday
                ? (model.dateString.isEmpty ? "Vandaag" : DateText.long(model.dateString))
                : "\(model.openCount) open"
        case .day:
            return model.dateString.isEmpty ? "\(model.openCount) open" : DateText.long(model.dateString)
        default:
            return "\(model.openCount) open"
        }
    }

    // MARK: - Day navigation (Today)

    private var dayNav: some View {
        HStack(spacing: 0) {
            Button { Task { await model.shiftDay(by: -1) } } label: {
                Image(systemName: "chevron.left")
                    .font(.system(size: 14, weight: .bold))
                    .foregroundStyle(Theme.ink)
                    .frame(width: 44, height: 32, alignment: .leading)
            }
            Spacer()
            if !model.isViewingToday {
                Button { Task { await model.goToToday() } } label: {
                    MonoLabel("Vandaag", color: Theme.accent)
                }
            }
            Spacer()
            Button { Task { await model.shiftDay(by: 1) } } label: {
                Image(systemName: "chevron.right")
                    .font(.system(size: 14, weight: .bold))
                    .foregroundStyle(Theme.ink)
                    .frame(width: 44, height: 32, alignment: .trailing)
            }
        }
        .padding(.horizontal, 20)
        .padding(.bottom, 8)
        .animation(.easeInOut(duration: 0.2), value: model.isViewingToday)
    }

    // MARK: - Quick add

    private var quickAddField: some View {
        VStack(spacing: 0) {
            HStack(spacing: 12) {
                AccentDot(size: 8)
                TextField("Snel toevoegen…", text: $model.quickAddText)
                    .font(.system(size: 18, weight: .semibold))
                    .foregroundStyle(Theme.ink)
                    .submitLabel(.done)
                    .focused($quickAddFocused)
                    .onSubmit {
                        Task {
                            await model.submitQuickAdd()
                            quickAddFocused = true
                        }
                    }
                ParseModeToggle(parsing: $model.quickAddParse)
            }
            .padding(.horizontal, 16)
            .padding(.vertical, 14)

            // The preview is the bottom half of the same card — it grows out of
            // the field when you type something parseable.
            ParsePreviewStrip(text: model.quickAddText, style: .attached, parse: model.quickAddParse)
        }
        .background(Theme.surface)
        .clipShape(RoundedRectangle(cornerRadius: 16, style: .continuous))
        .overlay(
            RoundedRectangle(cornerRadius: 16, style: .continuous)
                .strokeBorder(Theme.hairline, lineWidth: 1)
        )
        .padding(.horizontal, 16)
        .padding(.top, 4)
        .padding(.bottom, 12)
    }

    private func errorBanner(_ message: String) -> some View {
        Text(message)
            .font(.system(size: 13, weight: .medium))
            .foregroundStyle(Theme.accent)
            .frame(maxWidth: .infinity, alignment: .leading)
            .padding(.horizontal, 20)
            .padding(.vertical, 10)
    }

    private func undoBanner(_ todo: Todo) -> some View {
        HStack(spacing: 12) {
            Text("“\(todo.title)” verwijderd")
                .font(.system(size: 14, weight: .medium))
                .foregroundStyle(Theme.background)
                .lineLimit(1)
            Spacer(minLength: 8)
            Button {
                Task { await model.undoDelete() }
            } label: {
                Text("Ongedaan maken")
                    .font(.system(size: 14, weight: .bold))
                    .foregroundStyle(Theme.accent)
            }
        }
        .padding(.horizontal, 18)
        .padding(.vertical, 14)
        .background(Theme.ink)
        .clipShape(RoundedRectangle(cornerRadius: 12))
        .padding(.horizontal, 20)
        .padding(.bottom, 12)
        .transition(.move(edge: .bottom).combined(with: .opacity))
    }

    // MARK: - Toolbar

    @ToolbarContentBuilder
    private var boardToolbar: some ToolbarContent {
        ToolbarItem(placement: .topBarTrailing) {
            Menu {
                Picker("Sorteren", selection: sortBinding) {
                    ForEach(SortMode.allCases) { mode in
                        Label(mode.label, systemImage: mode.icon).tag(mode)
                    }
                }

                if model.sortMode == .manual && !model.todos.isEmpty {
                    Button { reordering = true } label: {
                        Label("Herorden…", systemImage: "arrow.up.arrow.down")
                    }
                }

                Divider()
                filterMenuItems

                if model.context == .today && model.isViewingToday {
                    Divider()
                    Button { Task { await model.resetRitual() } } label: {
                        Label("Ritueel opnieuw", systemImage: "arrow.counterclockwise")
                    }
                }

            } label: {
                Image(systemName: model.hasActiveFilter
                    ? "line.3.horizontal.decrease.circle.fill"
                    : "ellipsis.circle")
                    .foregroundStyle(model.hasActiveFilter ? Theme.accent : Theme.ink)
            }
        }
    }

    private var sortBinding: Binding<SortMode> {
        Binding(
            get: { model.sortMode },
            set: { mode in Task { await model.setSortMode(mode) } }
        )
    }

    @ViewBuilder
    private var filterMenuItems: some View {
        Menu {
            check("Alle prioriteiten", model.priorityFilter == nil) { model.priorityFilter = nil }
            ForEach([Priority.high, .normal, .low]) { priority in
                check(priority.label, model.priorityFilter == priority) {
                    model.priorityFilter = model.priorityFilter == priority ? nil : priority
                }
            }
        } label: {
            Label("Filter op prioriteit", systemImage: "flag")
        }

        if !model.availableTags.isEmpty {
            Menu {
                check("Alle tags", model.tagFilter == nil) { model.tagFilter = nil }
                ForEach(model.availableTags) { tag in
                    check(tag.name, model.tagFilter == tag.id) {
                        model.tagFilter = model.tagFilter == tag.id ? nil : tag.id
                    }
                }
            } label: {
                Label("Filter op tag", systemImage: "tag")
            }
        }

        if model.hasActiveFilter {
            Divider()
            Button(role: .destructive) { model.clearFilters() } label: {
                Label("Filter wissen", systemImage: "xmark.circle")
            }
        }
    }

    @ViewBuilder
    private func check(_ title: String, _ on: Bool, action: @escaping () -> Void) -> some View {
        Button(action: action) {
            if on {
                Label(title, systemImage: "checkmark")
            } else {
                Text(title)
            }
        }
    }

    // MARK: - Rows

    private func row(for todo: Todo) -> some View {
        TodoRow(
            todo: todo,
            showsSchedule: !model.isDayBoard,
            onToggle: { Task { await model.toggle(todo) } },
            onOpen: { sheet = .detail(todo) }
        ) {
            TodoMenu(
                todo: todo,
                isToday: model.context == .today,
                canRemoveFromList: model.canRemoveFromList,
                recurrencePresets: model.recurrencePresets,
                lists: model.addableLists,
                onOpen: { sheet = .detail(todo) },
                onToggle: { Task { await model.toggle(todo) } },
                onAddToday: { Task { await model.addToToday(todo) } },
                onPriority: { priority in Task { await model.setPriority(todo, priority) } },
                onAddSub: { sheet = .addSub(todo) },
                onRename: { sheet = .rename(todo) },
                onMove: { sheet = .move(todo) },
                onSetRecurrence: { preset in Task { await model.setRecurrence(todo, preset: preset) } },
                onCustomRecurrence: { sheet = .customRecurrence(todo) },
                onStopRecurrence: { Task { await model.stopRecurrence(todo) } },
                onAddToList: { listId in Task { await model.addToList(todo, listId: listId) } },
                onDuplicate: { Task { await model.duplicate(todo) } },
                onRemoveFromList: { Task { await model.removeFromList(todo) } },
                onDelete: { Task { await model.delete(todo) } }
            )
        }
    }

    // MARK: - Sheets

    @ViewBuilder
    private func sheetView(for route: TodoSheet) -> some View {
        switch route {
        case .detail(let todo):
            TodoDetailView(todo: todo) {
                await model.load()
            }
        case .rename(let todo):
            RenameSheet(initialTitle: todo.title) { newTitle in
                Task { await model.rename(todo, title: newTitle) }
            }
        case .move(let todo):
            MoveDateSheet { date in
                Task { await model.move(todo, toDate: date) }
            }
        case .addSub(let todo):
            AddSubTodoSheet { title in
                Task { await model.addSubTodo(todo, title: title) }
            }
        case .customRecurrence(let todo):
            RecurrenceSheet(anchorISO: model.recurrenceAnchorISO) { rrule in
                Task { await model.setCustomRecurrence(todo, rrule: rrule) }
            }
        }
    }
}

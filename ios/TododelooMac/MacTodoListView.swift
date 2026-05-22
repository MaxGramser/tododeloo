import SwiftUI

/// The middle column: a quick-add field, the todo list with native row
/// selection (which drives the inspector), and a sort/filter toolbar. For the
/// Today board it also gets day navigation.
struct MacTodoListView: View {
    @Bindable var model: BoardModel
    let lists: ListsModel
    @Binding var selectedTodoID: Int?
    @FocusState private var quickAddFocused: Bool

    var body: some View {
        VStack(spacing: 0) {
            quickAdd
            Divider()
            list
        }
        .navigationTitle(model.title)
        .navigationSubtitle(subtitle)
        .toolbar { toolbar }
    }

    private var subtitle: String {
        "\(model.openCount) open"
    }

    private var quickAdd: some View {
        HStack(spacing: 10) {
            Image(systemName: "plus.circle.fill")
                .foregroundStyle(Theme.accent)
                .font(.system(size: 15))
            TextField("Snel toevoegen…", text: $model.quickAddText)
                .textFieldStyle(.plain)
                .font(.system(size: 14, weight: .medium))
                .focused($quickAddFocused)
                .onSubmit {
                    Task {
                        await model.submitQuickAdd()
                        quickAddFocused = true
                    }
                }
        }
        .padding(.horizontal, 16)
        .padding(.vertical, 12)
    }

    private var list: some View {
        List(selection: $selectedTodoID) {
            ForEach(model.visibleTodos) { todo in
                MacTodoRow(todo: todo) { Task { await model.toggle(todo) } }
                    .tag(todo.id)
                    .contextMenu { MacTodoMenu(model: model, lists: lists, todo: todo) }
            }
        }
        .listStyle(.inset(alternatesRowBackgrounds: true))
        .overlay {
            if model.visibleTodos.isEmpty && model.hasLoaded {
                ContentUnavailableView(
                    model.hasActiveFilter ? "Geen resultaten" : "Niets hier",
                    systemImage: model.hasActiveFilter ? "line.3.horizontal.decrease.circle" : "checklist",
                    description: Text(model.hasActiveFilter ? "Geen taken voldoen aan dit filter." : "Voeg hierboven iets toe.")
                )
            }
        }
    }

    @ToolbarContentBuilder
    private var toolbar: some ToolbarContent {
        if model.context == .today {
            ToolbarItemGroup(placement: .navigation) {
                Button { Task { await model.shiftDay(by: -1) } } label: {
                    Image(systemName: "chevron.left")
                }
                if !model.isViewingToday {
                    Button("Vandaag") { Task { await model.goToToday() } }
                }
                Button { Task { await model.shiftDay(by: 1) } } label: {
                    Image(systemName: "chevron.right")
                }
            }
        }

        ToolbarItem {
            Menu {
                Picker("Sorteren", selection: sortBinding) {
                    ForEach(SortMode.allCases) { mode in
                        Label(mode.label, systemImage: mode.icon).tag(mode)
                    }
                }
                Divider()
                filterMenu
            } label: {
                Image(systemName: model.hasActiveFilter
                    ? "line.3.horizontal.decrease.circle.fill"
                    : "line.3.horizontal.decrease.circle")
            }
        }
    }

    private var sortBinding: Binding<SortMode> {
        Binding(get: { model.sortMode }, set: { mode in Task { await model.setSortMode(mode) } })
    }

    @ViewBuilder
    private var filterMenu: some View {
        Menu("Prioriteit") {
            checkButton("Alle prioriteiten", model.priorityFilter == nil) { model.priorityFilter = nil }
            ForEach([Priority.high, .normal, .low]) { p in
                checkButton(p.label, model.priorityFilter == p) {
                    model.priorityFilter = model.priorityFilter == p ? nil : p
                }
            }
        }
        if !model.availableTags.isEmpty {
            Menu("Tag") {
                checkButton("Alle tags", model.tagFilter == nil) { model.tagFilter = nil }
                ForEach(model.availableTags) { tag in
                    checkButton(tag.name, model.tagFilter == tag.id) {
                        model.tagFilter = model.tagFilter == tag.id ? nil : tag.id
                    }
                }
            }
        }
        if model.hasActiveFilter {
            Divider()
            Button("Filter wissen", role: .destructive) { model.clearFilters() }
        }
    }

    @ViewBuilder
    private func checkButton(_ title: String, _ on: Bool, action: @escaping () -> Void) -> some View {
        Button(action: action) {
            if on { Label(title, systemImage: "checkmark") } else { Text(title) }
        }
    }
}

/// One todo row in the list, with the recurrence/scheduling text shown inline.
struct MacTodoRow: View {
    let todo: Todo
    let onToggle: () -> Void

    var body: some View {
        HStack(alignment: .top, spacing: 10) {
            Button(action: onToggle) {
                Image(systemName: todo.isCompleted ? "largecircle.fill.circle" : "circle")
                    .font(.system(size: 16))
                    .foregroundStyle(toggleColor)
            }
            .buttonStyle(.plain)

            VStack(alignment: .leading, spacing: 3) {
                Text(todo.title)
                    .font(.system(size: 14))
                    .foregroundStyle(todo.isCompleted ? Theme.faint : Theme.ink)
                    .strikethrough(todo.isCompleted, color: Theme.faint)
                    .lineLimit(2)
                metadata
            }
            Spacer(minLength: 0)
        }
        .padding(.vertical, 3)
    }

    private var toggleColor: Color {
        if todo.isCompleted { return Theme.accent }
        return todo.priorityValue == .high ? Theme.accent : Theme.faint
    }

    @ViewBuilder
    private var metadata: some View {
        let tags = todo.tags ?? []
        let hasMeta = todo.priorityValue == .high
            || todo.isRecurring
            || !tags.isEmpty
            || todo.totalSubTodoCount > 0
            || !todo.otherMemberships.isEmpty

        if hasMeta {
            HStack(spacing: 6) {
                if todo.priorityValue == .high {
                    MonoLabel("Hoog", color: Theme.accent)
                }
                if let summary = todo.recurrence?.summary {
                    HStack(spacing: 3) {
                        Image(systemName: "repeat").font(.system(size: 9, weight: .semibold))
                        Text(summary.uppercased()).font(.mono(10, weight: .semibold)).tracking(1)
                    }
                    .foregroundStyle(Theme.faint)
                    .lineLimit(1)
                } else if todo.isRecurring {
                    Image(systemName: "repeat").font(.system(size: 9, weight: .semibold)).foregroundStyle(Theme.faint)
                }
                ForEach(tags) { tag in TagChip(tag: tag) }
                if todo.totalSubTodoCount > 0 {
                    MonoLabel("\(todo.openSubTodoCount)/\(todo.totalSubTodoCount)")
                }
                ForEach(todo.otherMemberships) { membership in
                    MonoLabel(membership.label, color: Theme.faint)
                }
            }
        }
    }
}

/// The right-click menu for a row. Reuses every BoardModel action.
struct MacTodoMenu: View {
    @Bindable var model: BoardModel
    let lists: ListsModel
    let todo: Todo

    var body: some View {
        Button(todo.isCompleted ? "Markeer onaf" : "Markeer af") {
            Task { await model.toggle(todo) }
        }
        if model.context != .today {
            Button("Naar vandaag") { Task { await model.addToToday(todo) } }
        }

        Menu("Prioriteit") {
            ForEach(Priority.allCases) { priority in
                Button { Task { await model.setPriority(todo, priority) } } label: {
                    if todo.priorityValue == priority {
                        Label(priority.label, systemImage: "checkmark")
                    } else {
                        Text(priority.label)
                    }
                }
            }
        }

        Menu(recurrenceTitle) {
            ForEach(model.recurrencePresets) { preset in
                Button { Task { await model.setRecurrence(todo, preset: preset.key) } } label: {
                    if todo.recurrence?.preset == preset.key {
                        Label(preset.label, systemImage: "checkmark")
                    } else {
                        Text(preset.label)
                    }
                }
            }
            if todo.isRecurring {
                Divider()
                Button("Stop herhaling", role: .destructive) { Task { await model.stopRecurrence(todo) } }
            }
        }

        if !model.addableLists.isEmpty {
            Menu("Zet op lijst") {
                ForEach(model.addableLists) { list in
                    Button(list.displayName) { Task { await model.addToList(todo, listId: list.id) } }
                }
            }
        }

        Button("Dupliceer") { Task { await model.duplicate(todo) } }

        if model.canRemoveFromList {
            Button("Uit deze lijst halen") { Task { await model.removeFromList(todo) } }
        }

        Divider()
        Button("Verwijder", role: .destructive) { Task { await model.delete(todo) } }
    }

    private var recurrenceTitle: String {
        if let summary = todo.recurrence?.summary { return "Herhaal · \(summary)" }
        return todo.isRecurring ? "Herhaal · aan" : "Herhaal"
    }
}

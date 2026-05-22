import SwiftUI

/// A single todo line. Tap the circle to toggle done, tap the body to open the
/// detail sheet, press-and-hold for the full action menu.
struct TodoRow<MenuContent: View>: View {
    let todo: Todo
    let onToggle: () -> Void
    let onOpen: () -> Void
    @ViewBuilder var menu: () -> MenuContent

    var body: some View {
        HStack(alignment: .top, spacing: 14) {
            Button(action: onToggle) {
                CompletionToggle(isCompleted: todo.isCompleted, priority: todo.priorityValue)
            }
            .buttonStyle(.plain)

            Button(action: onOpen) {
                VStack(alignment: .leading, spacing: 5) {
                    Text(todo.title)
                        .font(.system(size: 17, weight: .semibold))
                        .foregroundStyle(todo.isCompleted ? Theme.faint : Theme.ink)
                        .strikethrough(todo.isCompleted, color: Theme.faint)
                        .multilineTextAlignment(.leading)
                    metadata
                }
                .frame(maxWidth: .infinity, alignment: .leading)
            }
            .buttonStyle(.plain)
        }
        .padding(.horizontal, 20)
        .padding(.vertical, 13)
        .contentShape(Rectangle())
        .contextMenu { menu() }
    }

    @ViewBuilder
    private var metadata: some View {
        let tags = todo.tags ?? []
        let memberships = todo.otherMemberships
        let hasMeta = !tags.isEmpty
            || todo.totalSubTodoCount > 0
            || !memberships.isEmpty
            || todo.priorityValue == .high
            || todo.isRecurring

        if hasMeta {
            HStack(spacing: 7) {
                if todo.priorityValue == .high {
                    MonoLabel("Hoog", color: Theme.accent)
                }
                if let summary = todo.recurrence?.summary {
                    HStack(spacing: 4) {
                        Image(systemName: "repeat")
                            .font(.system(size: 10, weight: .semibold))
                        Text(summary.uppercased())
                            .font(.mono(11, weight: .semibold))
                            .tracking(1.4)
                            .lineLimit(1)
                    }
                    .foregroundStyle(Theme.faint)
                } else if todo.isRecurring {
                    Image(systemName: "repeat")
                        .font(.system(size: 10, weight: .semibold))
                        .foregroundStyle(Theme.faint)
                }
                ForEach(tags) { tag in
                    TagChip(tag: tag)
                }
                if todo.totalSubTodoCount > 0 {
                    MonoLabel("\(todo.openSubTodoCount)/\(todo.totalSubTodoCount)")
                }
                ForEach(memberships) { membership in
                    MonoLabel(membership.label, color: Theme.faint)
                }
            }
        }
    }
}

/// The press-and-hold action menu. Every action available on the todo lives
/// here so it is reachable without leaving the list.
struct TodoMenu: View {
    let todo: Todo
    let isToday: Bool
    let canRemoveFromList: Bool
    let recurrencePresets: [RecurrencePresetOption]
    let lists: [ListSummary]

    let onOpen: () -> Void
    let onToggle: () -> Void
    let onAddToday: () -> Void
    let onPriority: (Priority) -> Void
    let onAddSub: () -> Void
    let onRename: () -> Void
    let onMove: () -> Void
    let onSetRecurrence: (String) -> Void
    let onCustomRecurrence: () -> Void
    let onStopRecurrence: () -> Void
    let onAddToList: (Int) -> Void
    let onDuplicate: () -> Void
    let onRemoveFromList: () -> Void
    let onDelete: () -> Void

    private var recurrenceMenuTitle: String {
        if let summary = todo.recurrence?.summary {
            return "Herhaal · \(summary)"
        }
        return todo.isRecurring ? "Herhaal · aan" : "Herhaal"
    }

    @ViewBuilder
    private var recurrencePresetButtons: some View {
        ForEach(recurrencePresets) { preset in
            Button {
                onSetRecurrence(preset.key)
            } label: {
                if todo.recurrence?.preset == preset.key {
                    Label(preset.label, systemImage: "checkmark")
                } else {
                    Text(preset.label)
                }
            }
        }
    }

    var body: some View {
        Button(action: onOpen) {
            Label("Openen", systemImage: "square.and.pencil")
        }
        Button(action: onToggle) {
            Label(
                todo.isCompleted ? "Markeer onaf" : "Markeer af",
                systemImage: todo.isCompleted ? "circle" : "checkmark.circle"
            )
        }

        if !isToday {
            Button(action: onAddToday) {
                Label("Naar vandaag", systemImage: "sun.max")
            }
        }

        Menu {
            ForEach(Priority.allCases) { priority in
                Button {
                    onPriority(priority)
                } label: {
                    if todo.priorityValue == priority {
                        Label(priority.label, systemImage: "checkmark")
                    } else {
                        Text(priority.label)
                    }
                }
            }
        } label: {
            Label("Prioriteit", systemImage: "flag")
        }

        Button(action: onAddSub) {
            Label("Subtaak toevoegen", systemImage: "plus.circle")
        }
        Button(action: onRename) {
            Label("Hernoemen", systemImage: "pencil")
        }
        Button(action: onMove) {
            Label("Verplaats naar datum", systemImage: "calendar")
        }

        Menu {
            if let summary = todo.recurrence?.summary {
                Section("Nu: \(summary)") {
                    recurrencePresetButtons
                }
            } else {
                recurrencePresetButtons
            }
            Divider()
            Button(action: onCustomRecurrence) {
                Label("Aangepast…", systemImage: "slider.horizontal.3")
            }
            if todo.isRecurring {
                Divider()
                Button(role: .destructive, action: onStopRecurrence) {
                    Label("Stop herhaling", systemImage: "xmark")
                }
            }
        } label: {
            Label(recurrenceMenuTitle, systemImage: "repeat")
        }

        if !lists.isEmpty {
            Menu {
                ForEach(lists) { list in
                    Button {
                        onAddToList(list.id)
                    } label: {
                        Label(list.displayName, systemImage: "tray")
                    }
                }
            } label: {
                Label("Zet op lijst", systemImage: "tray.full")
            }
        }

        Button(action: onDuplicate) {
            Label("Dupliceren", systemImage: "plus.square.on.square")
        }

        if canRemoveFromList {
            Button(role: .destructive, action: onRemoveFromList) {
                Label("Uit deze lijst halen", systemImage: "minus.circle")
            }
        }

        Divider()

        Button(role: .destructive, action: onDelete) {
            Label("Verwijderen", systemImage: "trash")
        }
    }
}

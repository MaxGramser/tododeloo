import SwiftUI

/// The leftmost column: Vandaag, Alles, and the custom lists, plus a button to
/// create a new list. Selection drives the middle column. Custom lists rename
/// inline on double-click (or via the context menu) and can be deleted.
struct MacSidebar: View {
    let lists: ListsModel
    let upcoming: UpcomingModel
    @Binding var selection: MacSection?
    let onNewList: () -> Void

    @State private var renamingListID: Int?
    @State private var renameText = ""

    var body: some View {
        List(selection: $selection) {
            Section {
                Label("Vandaag", systemImage: "sun.max")
                    .tag(MacSection.today)
                Label("Alles", systemImage: "tray.full")
                    .badge(lists.master?.openCount ?? 0)
                    .tag(MacSection.master)
            }

            if !scheduledDays.isEmpty {
                Section("Binnenkort") {
                    ForEach(scheduledDays, id: \.id) { day in
                        Label {
                            Text(DateText.relative(day.date ?? ""))
                        } icon: {
                            Image(systemName: "calendar")
                        }
                        .badge((day.todos ?? []).filter { !$0.isCompleted }.count)
                        .tag(MacSection.day(day.date ?? ""))
                    }
                }
            }

            if !lists.customLists.isEmpty {
                Section("Lijsten") {
                    ForEach(lists.customLists) { list in
                        SidebarListRow(
                            list: list,
                            isRenaming: renamingListID == list.id,
                            renameText: $renameText,
                            onStartRename: { startRename(list) },
                            onCommit: { commitRename(list) },
                            onCancel: { renamingListID = nil },
                            onDelete: { Task { await lists.delete(list) } }
                        )
                        .tag(MacSection.custom(list.id))
                    }
                }
            }
        }
        .listStyle(.sidebar)
        .navigationTitle("Tododeloo")
        .safeAreaInset(edge: .bottom) {
            Button(action: onNewList) {
                Label("Nieuwe lijst", systemImage: "plus")
                    .frame(maxWidth: .infinity, alignment: .leading)
                    .contentShape(Rectangle())
            }
            .buttonStyle(.plain)
            .foregroundStyle(Theme.muted)
            .padding(.horizontal, 14)
            .padding(.vertical, 10)
        }
    }

    /// Upcoming daily lists that actually carry a date and todos.
    private var scheduledDays: [TodoList] {
        upcoming.days.filter { $0.date != nil && !($0.todos ?? []).isEmpty }
    }

    private func startRename(_ list: ListSummary) {
        renameText = list.displayName
        renamingListID = list.id
    }

    private func commitRename(_ list: ListSummary) {
        guard renamingListID == list.id else { return }
        let trimmed = renameText.trimmingCharacters(in: .whitespacesAndNewlines)
        renamingListID = nil
        guard !trimmed.isEmpty, trimmed != list.displayName else { return }
        Task { await lists.rename(list, name: trimmed) }
    }
}

private struct SidebarListRow: View {
    let list: ListSummary
    let isRenaming: Bool
    @Binding var renameText: String
    let onStartRename: () -> Void
    let onCommit: () -> Void
    let onCancel: () -> Void
    let onDelete: () -> Void
    @FocusState private var focused: Bool

    var body: some View {
        // The icon lives in its own slot so it never disappears while editing —
        // only the title swaps between a label and the rename field.
        Label {
            if isRenaming {
                TextField("", text: $renameText)
                    .textFieldStyle(.plain)
                    .focused($focused)
                    .onSubmit(onCommit)
                    .onExitCommand(perform: onCancel)
                    .onChange(of: focused) { _, isFocused in
                        // Clicking away commits, so editing never stays stuck.
                        if !isFocused { onCommit() }
                    }
                    .onAppear { focused = true }
            } else {
                Text(list.displayName)
                    // Double-click the name to rename; single click still selects.
                    .simultaneousGesture(TapGesture(count: 2).onEnded { onStartRename() })
            }
        } icon: {
            Image(systemName: "list.bullet")
        }
        .badge(list.openCount)
        .contextMenu {
            Button("Hernoem", action: onStartRename)
            Button("Verwijder", role: .destructive, action: onDelete)
        }
    }
}

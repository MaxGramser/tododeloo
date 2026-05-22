import SwiftUI

/// The leftmost column: Vandaag, Alles, and the custom lists, plus a button to
/// create a new list. Selection drives the middle column.
struct MacSidebar: View {
    let lists: ListsModel
    @Binding var selection: MacSection?
    let onNewList: () -> Void

    var body: some View {
        List(selection: $selection) {
            Section {
                Label("Vandaag", systemImage: "sun.max")
                    .tag(MacSection.today)
                Label("Alles", systemImage: "tray.full")
                    .badge(lists.master?.openCount ?? 0)
                    .tag(MacSection.master)
            }

            if !lists.customLists.isEmpty {
                Section("Lijsten") {
                    ForEach(lists.customLists) { list in
                        Label(list.displayName, systemImage: "list.bullet")
                            .badge(list.openCount)
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
}

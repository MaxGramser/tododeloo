import SwiftUI

private enum ListsSheet: Identifiable {
    case create
    case rename(ListSummary)

    var id: String {
        switch self {
        case .create: return "create"
        case .rename(let list): return "rename-\(list.id)"
        }
    }
}

struct ListsView: View {
    @Environment(Session.self) private var session
    @State private var model = ListsModel()
    @State private var sheet: ListsSheet?

    var body: some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 0) {
                header

                NavigationLink(value: BoardContext.master) {
                    ListRow(name: "Alles", subtitle: masterSubtitle, emphasized: true)
                }
                .buttonStyle(.plain)
                divider

                ForEach(model.customLists) { list in
                    NavigationLink(value: BoardContext.custom(id: list.id)) {
                        ListRow(name: list.displayName, subtitle: "\(list.openCount) open")
                    }
                    .buttonStyle(.plain)
                    .contextMenu {
                        Button {
                            sheet = .rename(list)
                        } label: {
                            Label("Hernoemen", systemImage: "pencil")
                        }
                        Button(role: .destructive) {
                            Task { await model.delete(list) }
                        } label: {
                            Label("Verwijderen", systemImage: "trash")
                        }
                    }
                    divider
                }

                Button {
                    sheet = .create
                } label: {
                    HStack(spacing: 12) {
                        Image(systemName: "plus")
                            .font(.system(size: 16, weight: .bold))
                            .foregroundStyle(Theme.accent)
                        Text("Nieuwe lijst")
                            .font(.system(size: 17, weight: .semibold))
                            .foregroundStyle(Theme.muted)
                        Spacer()
                    }
                    .padding(.horizontal, 20)
                    .padding(.vertical, 18)
                    .contentShape(Rectangle())
                }
                .buttonStyle(.plain)
            }
            .padding(.bottom, 40)
        }
        .background(Theme.background.ignoresSafeArea())
        .navigationDestination(for: BoardContext.self) { context in
            ListDetailView(context: context)
        }
        .refreshable { await model.load() }
        .sheet(item: $sheet) { route in
            switch route {
            case .create:
                CreateListSheet { name in
                    Task { await model.createList(name: name) }
                }
            case .rename(let list):
                RenameSheet(initialTitle: list.displayName) { name in
                    Task { await model.rename(list, name: name) }
                }
            }
        }
        .task {
            model.onUnauthorized = { session.handleUnauthorized() }
            if !model.hasLoaded {
                await model.load()
            }
        }
    }

    private var masterSubtitle: String {
        guard let master = model.master else { return "—" }
        return "\(master.openCount) open"
    }

    private var header: some View {
        VStack(alignment: .leading, spacing: 6) {
            MonoLabel("Overzicht")
            HStack(alignment: .firstTextBaseline, spacing: 8) {
                Text("Lijsten")
                    .font(.display(40))
                    .foregroundStyle(Theme.ink)
                AccentDot(size: 9)
            }
        }
        .frame(maxWidth: .infinity, alignment: .leading)
        .padding(.horizontal, 20)
        .padding(.top, 12)
        .padding(.bottom, 14)
    }

    private var divider: some View {
        Rectangle()
            .fill(Theme.hairline)
            .frame(height: 1)
            .padding(.leading, 20)
    }
}

private struct ListRow: View {
    let name: String
    let subtitle: String
    var emphasized = false

    var body: some View {
        HStack(spacing: 14) {
            VStack(alignment: .leading, spacing: 4) {
                Text(name)
                    .font(.system(size: emphasized ? 22 : 19, weight: emphasized ? .bold : .semibold))
                    .foregroundStyle(Theme.ink)
                MonoLabel(subtitle)
            }
            Spacer()
            Image(systemName: "chevron.right")
                .font(.system(size: 13, weight: .bold))
                .foregroundStyle(Theme.faint)
        }
        .padding(.horizontal, 20)
        .padding(.vertical, 18)
        .contentShape(Rectangle())
    }
}

struct ListDetailView: View {
    @State private var model: BoardModel

    init(context: BoardContext) {
        _model = State(initialValue: BoardModel(context: context))
    }

    var body: some View {
        BoardView(model: model)
            .navigationBarTitleDisplayMode(.inline)
    }
}

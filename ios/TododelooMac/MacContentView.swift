import SwiftUI

/// Which collection the middle column shows. Maps onto the shared BoardContext.
enum MacSection: Hashable {
    case today
    case master
    case custom(Int)

    var boardContext: BoardContext {
        switch self {
        case .today: return .today
        case .master: return .master
        case .custom(let id): return .custom(id: id)
        }
    }
}

/// The three-column main window: sidebar · todo list · detail inspector.
struct MacContentView: View {
    @Environment(Session.self) private var session
    @State private var lists = ListsModel()
    @State private var selection: MacSection? = .today
    @State private var board = BoardModel(context: .today)
    @State private var selectedTodoID: Int?
    @State private var creatingList = false

    var body: some View {
        NavigationSplitView {
            MacSidebar(lists: lists, selection: $selection) { creatingList = true }
                .navigationSplitViewColumnWidth(min: 210, ideal: 240, max: 300)
        } content: {
            MacTodoListView(model: board, lists: lists, selectedTodoID: $selectedTodoID)
                .navigationSplitViewColumnWidth(min: 340, ideal: 420, max: 640)
        } detail: {
            MacInspectorView(board: board, lists: lists, todoID: $selectedTodoID)
                .navigationSplitViewColumnWidth(min: 290, ideal: 330, max: 460)
        }
        .navigationSplitViewStyle(.balanced)
        .task {
            lists.onUnauthorized = { session.handleUnauthorized() }
            board.onUnauthorized = { session.handleUnauthorized() }
            await lists.load()
            await board.load()
        }
        .onChange(of: selection) { _, newValue in
            guard let newValue else { return }
            let model = BoardModel(context: newValue.boardContext)
            model.onUnauthorized = { session.handleUnauthorized() }
            selectedTodoID = nil
            board = model
            Task { await board.load() }
        }
        .sheet(isPresented: $creatingList) {
            MacCreateListSheet { name in
                Task {
                    await lists.createList(name: name)
                    await lists.load()
                }
            }
        }
    }
}

/// A small sheet to name a new custom list.
struct MacCreateListSheet: View {
    @Environment(\.dismiss) private var dismiss
    let onCreate: (String) -> Void
    @State private var name = ""

    var body: some View {
        VStack(alignment: .leading, spacing: 18) {
            Text("Nieuwe lijst")
                .font(.headline)
            TextField("Naam van de lijst", text: $name)
                .textFieldStyle(.roundedBorder)
                .onSubmit(create)
                .frame(width: 280)
            HStack {
                Spacer()
                Button("Annuleer") { dismiss() }
                Button("Aanmaken", action: create)
                    .buttonStyle(.borderedProminent)
                    .tint(Theme.ink)
                    .disabled(name.trimmingCharacters(in: .whitespaces).isEmpty)
            }
        }
        .padding(24)
    }

    private func create() {
        let trimmed = name.trimmingCharacters(in: .whitespacesAndNewlines)
        guard !trimmed.isEmpty else { return }
        onCreate(trimmed)
        dismiss()
    }
}

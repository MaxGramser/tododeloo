import SwiftUI

/// Renders one board (Today / Alles / a custom list): editorial header, a fast
/// quick-add field, and the todo rows with their context menus and sheets.
struct BoardView: View {
    @Environment(Session.self) private var session
    @Bindable var model: BoardModel
    var showsHeader = true

    @State private var sheet: TodoSheet?
    @FocusState private var quickAddFocused: Bool

    var body: some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 0) {
                if showsHeader {
                    header
                }
                quickAddField

                if let message = model.errorMessage {
                    errorBanner(message)
                }

                if model.todos.isEmpty && model.hasLoaded {
                    EmptyStateView(
                        headline: "Niets hier.",
                        subtitle: "Typ hierboven om iets toe te voegen."
                    )
                } else {
                    ForEach(model.todos) { todo in
                        row(for: todo)
                        Rectangle()
                            .fill(Theme.hairline)
                            .frame(height: 1)
                            .padding(.leading, 20)
                    }
                }
            }
            .padding(.bottom, 40)
        }
        .background(Theme.background.ignoresSafeArea())
        .scrollDismissesKeyboard(.interactively)
        .refreshable { await model.load() }
        .sheet(item: $sheet) { route in
            sheetView(for: route)
        }
        .task {
            model.onUnauthorized = { session.handleUnauthorized() }
            if !model.hasLoaded {
                await model.load()
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
            return model.dateString.isEmpty ? "Vandaag" : DateText.long(model.dateString)
        default:
            return "\(model.openCount) open"
        }
    }

    // MARK: - Quick add

    private var quickAddField: some View {
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
        }
        .padding(.horizontal, 20)
        .padding(.vertical, 16)
        .background(Theme.surface)
        .overlay(alignment: .bottom) {
            Rectangle().fill(Theme.ink.opacity(0.85)).frame(height: 2)
        }
    }

    private func errorBanner(_ message: String) -> some View {
        Text(message)
            .font(.system(size: 13, weight: .medium))
            .foregroundStyle(Theme.accent)
            .frame(maxWidth: .infinity, alignment: .leading)
            .padding(.horizontal, 20)
            .padding(.vertical, 10)
    }

    // MARK: - Rows

    private func row(for todo: Todo) -> some View {
        TodoRow(
            todo: todo,
            onToggle: { Task { await model.toggle(todo) } },
            onOpen: { sheet = .detail(todo) }
        ) {
            TodoMenu(
                todo: todo,
                isToday: model.context == .today,
                canRemoveFromList: model.canRemoveFromList,
                onOpen: { sheet = .detail(todo) },
                onToggle: { Task { await model.toggle(todo) } },
                onAddToday: { Task { await model.addToToday(todo) } },
                onPriority: { priority in Task { await model.setPriority(todo, priority) } },
                onAddSub: { sheet = .addSub(todo) },
                onRename: { sheet = .rename(todo) },
                onMove: { sheet = .move(todo) },
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
        }
    }
}

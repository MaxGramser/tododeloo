import SwiftUI

/// "Binnenkort": the days ahead that hold scheduled todos, grouped by day so you
/// can see at a glance when each becomes relevant. Tap a day header to open its
/// full board; press-and-hold a todo to triage it (reschedule, pull to today,
/// complete, delete) without leaving the agenda.
struct UpcomingView: View {
    @Environment(Session.self) private var session
    @State private var model = UpcomingModel()
    @State private var detail: Todo?
    @State private var moveTarget: Todo?

    var body: some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 0) {
                header
                if let message = model.errorMessage {
                    errorBanner(message)
                }
                content
            }
            .padding(.bottom, 40)
        }
        .background(Theme.background.ignoresSafeArea())
        .scrollDismissesKeyboard(.interactively)
        .refreshable { await model.load() }
        .task {
            model.onUnauthorized = { session.handleUnauthorized() }
            if !model.hasLoaded {
                await model.load()
            }
        }
        .sheet(item: $detail) { todo in
            TodoDetailView(todo: todo) { await model.load() }
        }
        .sheet(item: $moveTarget) { todo in
            MoveDateSheet { date in
                Task { await model.move(todo, toDate: date) }
            }
        }
    }

    // MARK: - Header

    private var header: some View {
        VStack(alignment: .leading, spacing: 6) {
            MonoLabel("Gepland")
            HStack(alignment: .firstTextBaseline, spacing: 8) {
                Text("Binnenkort")
                    .font(.display(40))
                    .foregroundStyle(Theme.ink)
                AccentDot(size: 9)
            }
        }
        .frame(maxWidth: .infinity, alignment: .leading)
        .padding(.horizontal, 20)
        .padding(.top, 12)
        .padding(.bottom, 12)
    }

    // MARK: - Content

    @ViewBuilder
    private var content: some View {
        if model.isEmpty && model.hasLoaded {
            EmptyStateView(
                headline: "Niets gepland.",
                subtitle: "Verplaats een taak naar een datum en die verschijnt hier."
            )
        } else {
            ForEach(model.days) { day in
                if let date = day.date, let todos = day.todos, !todos.isEmpty {
                    daySection(date: date, todos: todos)
                }
            }
        }
    }

    private func daySection(date: String, todos: [Todo]) -> some View {
        VStack(alignment: .leading, spacing: 0) {
            NavigationLink {
                DayBoardView(date: date)
            } label: {
                dayHeader(date: date, openCount: todos.filter { !$0.isCompleted }.count)
            }
            .buttonStyle(.plain)

            ForEach(todos) { todo in
                TodoRow(
                    todo: todo,
                    onToggle: { Task { await model.toggle(todo) } },
                    onOpen: { detail = todo }
                ) {
                    rowMenu(todo)
                }
                Rectangle()
                    .fill(Theme.hairline)
                    .frame(height: 1)
                    .padding(.leading, 20)
            }
        }
        .padding(.top, 26)
    }

    private func dayHeader(date: String, openCount: Int) -> some View {
        HStack(alignment: .firstTextBaseline, spacing: 10) {
            Text(DateText.relative(date))
                .font(.display(22))
                .foregroundStyle(Theme.ink)
            Text(DateText.long(date))
                .font(.mono(11, weight: .semibold))
                .tracking(1.2)
                .foregroundStyle(Theme.faint)
                .lineLimit(1)
            Spacer(minLength: 8)
            if openCount > 0 {
                MonoLabel("\(openCount)", color: Theme.faint)
            }
            Image(systemName: "chevron.right")
                .font(.system(size: 12, weight: .bold))
                .foregroundStyle(Theme.faint)
        }
        .padding(.horizontal, 20)
        .padding(.bottom, 10)
        .contentShape(Rectangle())
    }

    @ViewBuilder
    private func rowMenu(_ todo: Todo) -> some View {
        Button(action: { detail = todo }) {
            Label("Openen", systemImage: "square.and.pencil")
        }
        if !todo.hasSubTodos {
            Button(action: { Task { await model.toggle(todo) } }) {
                Label(
                    todo.isCompleted ? "Markeer onaf" : "Markeer af",
                    systemImage: todo.isCompleted ? "circle" : "checkmark.circle"
                )
            }
        }
        Button(action: { Task { await model.addToToday(todo) } }) {
            Label("Naar vandaag", systemImage: "sun.max")
        }
        Button(action: { moveTarget = todo }) {
            Label("Verplaats naar datum", systemImage: "calendar")
        }
        Divider()
        Button(role: .destructive, action: { Task { await model.delete(todo) } }) {
            Label("Verwijderen", systemImage: "trash")
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
}

/// A specific dated day opened from the agenda. Reuses the entire board — rows,
/// the full press-and-hold menu, quick-add and day paging — anchored on its date.
struct DayBoardView: View {
    @State private var model: BoardModel

    init(date: String) {
        _model = State(initialValue: BoardModel(context: .day(date: date)))
    }

    var body: some View {
        BoardView(model: model)
            .navigationBarTitleDisplayMode(.inline)
    }
}

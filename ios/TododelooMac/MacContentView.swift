import SwiftUI

/// Which collection the middle column shows. Maps onto the shared BoardContext.
enum MacSection: Hashable {
    case today
    /// A specific upcoming day, selected from the "Binnenkort" sidebar section.
    case day(String)
    case master
    case custom(Int)

    var boardContext: BoardContext {
        switch self {
        case .today: return .today
        case .day(let date): return .day(date: date)
        case .master: return .master
        case .custom(let id): return .custom(id: id)
        }
    }
}

/// The three-column main window: sidebar · todo list · detail inspector.
struct MacContentView: View {
    @Environment(Session.self) private var session
    @State private var lists = ListsModel()
    @State private var upcoming = UpcomingModel()
    @State private var selection: MacSection? = .today
    @State private var board = BoardModel(context: .today)
    @State private var selectedTodoID: Int?
    @State private var creatingList = false

    // Menu commands can't reach into the list's @FocusState / sheet state
    // directly, so they bump a token the list watches and acts on.
    @State private var focusQuickAddToken = 0
    @State private var renameSelectedToken = 0
    @State private var moveSelectedToken = 0

    var body: some View {
        NavigationSplitView {
            MacSidebar(lists: lists, upcoming: upcoming, selection: $selection) { creatingList = true }
                .navigationSplitViewColumnWidth(min: 210, ideal: 240, max: 300)
        } content: {
            Group {
                if board.needsRitual {
                    MacRitualView(model: board)
                } else {
                    MacTodoListView(
                        model: board,
                        lists: lists,
                        selectedTodoID: $selectedTodoID,
                        focusQuickAddToken: focusQuickAddToken,
                        renameSelectedToken: renameSelectedToken,
                        moveSelectedToken: moveSelectedToken
                    )
                }
            }
            .navigationSplitViewColumnWidth(min: 340, ideal: 420, max: 640)
        } detail: {
            MacInspectorView(board: board, lists: lists, todoID: $selectedTodoID)
                .navigationSplitViewColumnWidth(min: 290, ideal: 330, max: 460)
        }
        .navigationSplitViewStyle(.balanced)
        .toastHost()
        .focusedSceneValue(\.boardActions, makeBoardActions())
        .task {
            lists.onUnauthorized = { session.handleUnauthorized() }
            upcoming.onUnauthorized = { session.handleUnauthorized() }
            board.onUnauthorized = { session.handleUnauthorized() }
            await lists.load()
            await upcoming.load()
            await board.load()
        }
        .onChange(of: selection) { _, newValue in
            guard let newValue else { return }
            let model = BoardModel(context: newValue.boardContext)
            model.onUnauthorized = { session.handleUnauthorized() }
            // Refresh the agenda whenever the board reloads, so rescheduling a
            // todo updates the "Binnenkort" sidebar counts and days.
            model.onDidLoad = { Task { await upcoming.load() } }
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

    /// Rebuilt on every render so the menu reflects the current board. Closures
    /// read live state at call time; flags drive each item's enabled state.
    private func makeBoardActions() -> BoardActions {
        let selectedTodo = board.todos.first { $0.id == selectedTodoID }
        let firstUpcoming = upcoming.days.first { $0.date != nil && !($0.todos ?? []).isEmpty }?.date

        return BoardActions(
            hasSelectedTodo: selectedTodo != nil,
            selectedIsCompleted: selectedTodo?.isCompleted ?? false,
            isDayBoard: board.isDayBoard && !board.needsRitual,
            isToday: board.context == .today && board.isViewingToday && !board.needsRitual,
            hasUpcoming: firstUpcoming != nil,
            newTodo: { focusQuickAddToken += 1 },
            newList: { creatingList = true },
            toggleSelected: {
                if let todo = board.todos.first(where: { $0.id == selectedTodoID }) {
                    Task { await board.toggle(todo) }
                }
            },
            renameSelected: { renameSelectedToken += 1 },
            moveSelected: { moveSelectedToken += 1 },
            deleteSelected: {
                if let todo = board.todos.first(where: { $0.id == selectedTodoID }) {
                    Task { await board.delete(todo) }
                }
            },
            goToday: {
                if selection == .today {
                    Task { await board.goToToday() }
                } else {
                    selection = .today
                }
            },
            goMaster: { selection = .master },
            goUpcoming: {
                if let date = upcoming.days.first(where: { $0.date != nil && !($0.todos ?? []).isEmpty })?.date {
                    selection = .day(date)
                }
            },
            previousDay: { Task { await board.shiftDay(by: -1) } },
            nextDay: { Task { await board.shiftDay(by: 1) } },
            resetRitual: { Task { await board.resetRitual() } }
        )
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

/// The morning ritual on Mac, mirroring the web Day.vue: jot a few fresh ones up
/// top, then tick what carries over from the previous workday, earlier days and
/// master, and start the day. Uses the shared BoardModel state + startDay.
struct MacRitualView: View {
    @Bindable var model: BoardModel

    @State private var selectedCarry: Set<Int> = []
    @State private var selectedEarlier: Set<Int> = []
    @State private var selectedMaster: Set<Int> = []
    @State private var newTitles: [String] = []
    @State private var draft = ""
    @State private var isStarting = false

    var body: some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 0) {
                header
                    .padding(.bottom, 22)

                newInput
                newList

                selectableSection(
                    title: "vorige werkdag",
                    trailing: previousLabel.isEmpty ? nil : "van \(previousLabel)",
                    emptyText: "Niets blijven liggen.",
                    todos: model.carryOverCandidates,
                    selection: $selectedCarry
                )

                if !model.earlierCandidates.isEmpty {
                    selectableSection(
                        title: "eerder",
                        trailing: "langer blijven liggen",
                        emptyText: nil,
                        todos: model.earlierCandidates,
                        selection: $selectedEarlier
                    )
                }

                selectableSection(
                    title: "master",
                    trailing: nil,
                    emptyText: "Master is leeg.",
                    todos: model.masterOpenTodos,
                    selection: $selectedMaster
                )

                if !model.preScheduled.isEmpty {
                    preScheduledSection
                }

                startButton
            }
            .padding(.horizontal, 28)
            .padding(.top, 22)
            .padding(.bottom, 48)
            .frame(maxWidth: 620, alignment: .leading)
            .frame(maxWidth: .infinity, alignment: .leading)
        }
        .background(Theme.background)
        .navigationTitle("Vandaag")
    }

    private var header: some View {
        VStack(alignment: .leading, spacing: 6) {
            MonoLabel("vandaag")
            HStack(alignment: .firstTextBaseline, spacing: 8) {
                Text("Vandaag").font(.display(34)).foregroundStyle(Theme.ink)
                AccentDot(size: 8)
            }
            if !model.dateString.isEmpty {
                Text(DateText.long(model.dateString))
                    .font(.system(size: 14))
                    .foregroundStyle(Theme.muted)
            }
        }
    }

    private var newInput: some View {
        HStack(spacing: 12) {
            Image(systemName: "plus")
                .font(.system(size: 13, weight: .bold))
                .foregroundStyle(Theme.faint)
            TextField("Iets nieuws voor vandaag…", text: $draft)
                .textFieldStyle(.plain)
                .font(.system(size: 14))
                .foregroundStyle(Theme.ink)
                .onSubmit(addDraft)
            Spacer(minLength: 0)
        }
        .padding(.vertical, 12)
        .overlay(alignment: .bottom) { divider }
    }

    @ViewBuilder
    private var newList: some View {
        ForEach(Array(newTitles.enumerated()), id: \.offset) { index, title in
            HStack(spacing: 12) {
                Circle().fill(Theme.accent).frame(width: 6, height: 6)
                Text(title).font(.system(size: 14)).foregroundStyle(Theme.ink)
                Spacer()
                Button { newTitles.remove(at: index) } label: {
                    Image(systemName: "xmark").font(.system(size: 10, weight: .bold)).foregroundStyle(Theme.faint)
                }
                .buttonStyle(.plain)
            }
            .padding(.vertical, 8)
            .overlay(alignment: .bottom) { divider.opacity(0.5) }
        }
    }

    private func selectableSection(title: String, trailing: String?, emptyText: String?, todos: [Todo], selection: Binding<Set<Int>>) -> some View {
        VStack(alignment: .leading, spacing: 0) {
            sectionHeader(title: title, count: todos.count, trailing: trailing, accent: false)
            if todos.isEmpty {
                if let emptyText {
                    Text(emptyText)
                        .font(.system(size: 14))
                        .foregroundStyle(Theme.faint)
                        .padding(.vertical, 10)
                }
            } else {
                ForEach(todos) { todo in
                    Button {
                        toggle(todo.id, in: selection)
                    } label: {
                        HStack(spacing: 12) {
                            checkbox(selection.wrappedValue.contains(todo.id))
                            Text(todo.title)
                                .font(.system(size: 14))
                                .foregroundStyle(Theme.ink)
                                .multilineTextAlignment(.leading)
                            Spacer()
                        }
                        .padding(.vertical, 8)
                        .contentShape(Rectangle())
                    }
                    .buttonStyle(.plain)
                    .overlay(alignment: .bottom) { divider.opacity(0.5) }
                }
            }
        }
        .padding(.top, 30)
    }

    private var preScheduledSection: some View {
        VStack(alignment: .leading, spacing: 0) {
            sectionHeader(title: "al gepland", count: model.preScheduled.count, trailing: nil, accent: true)
            ForEach(model.preScheduled) { todo in
                HStack(spacing: 12) {
                    Circle().fill(Theme.accent).frame(width: 6, height: 6)
                    Text(todo.title).font(.system(size: 14)).foregroundStyle(Theme.ink)
                    Spacer()
                }
                .padding(.vertical, 8)
                .overlay(alignment: .bottom) { divider.opacity(0.5) }
            }
        }
        .padding(.top, 30)
    }

    private func sectionHeader(title: String, count: Int, trailing: String?, accent: Bool) -> some View {
        HStack(alignment: .firstTextBaseline, spacing: 6) {
            MonoLabel(title, color: accent ? Theme.accent : Theme.muted)
            if count > 0 {
                Text("(\(count))")
                    .font(.mono(10))
                    .foregroundStyle((accent ? Theme.accent : Theme.faint).opacity(0.6))
            }
            if let trailing {
                Spacer()
                Text(trailing).font(.mono(10)).foregroundStyle(Theme.faint)
            }
        }
        .padding(.bottom, 8)
    }

    private func checkbox(_ selected: Bool) -> some View {
        ZStack {
            Circle().strokeBorder(selected ? Color.clear : Theme.faint, lineWidth: 1.5)
            if selected {
                Circle().fill(Theme.accent)
                Image(systemName: "checkmark")
                    .font(.system(size: 9, weight: .bold))
                    .foregroundStyle(Theme.background)
            }
        }
        .frame(width: 18, height: 18)
    }

    private var divider: some View {
        Rectangle().fill(Theme.hairline).frame(height: 1)
    }

    private var startButton: some View {
        HStack {
            Spacer()
            Button(action: start) {
                HStack(spacing: 8) {
                    Text(isStarting ? "Bezig…" : "Start de dag")
                    Image(systemName: "arrow.right")
                }
                .font(.system(size: 14, weight: .semibold))
                .foregroundStyle(Theme.background)
                .padding(.horizontal, 22)
                .padding(.vertical, 12)
                .background(Theme.ink)
            }
            .buttonStyle(.plain)
            .disabled(isStarting)
        }
        .padding(.top, 36)
    }

    private var previousLabel: String {
        model.previousWorkday.isEmpty ? "" : DateText.long(model.previousWorkday)
    }

    private func toggle(_ id: Int, in selection: Binding<Set<Int>>) {
        if selection.wrappedValue.contains(id) {
            selection.wrappedValue.remove(id)
        } else {
            selection.wrappedValue.insert(id)
        }
    }

    private func addDraft() {
        let trimmed = draft.trimmingCharacters(in: .whitespacesAndNewlines)
        guard !trimmed.isEmpty else { return }
        newTitles.append(trimmed)
        draft = ""
    }

    private func start() {
        addDraft()
        isStarting = true
        let carryOverIds = Array(selectedCarry.union(selectedEarlier).union(selectedMaster))
        Task {
            await model.startDay(carryOverIds: carryOverIds, newTitles: newTitles)
            isStarting = false
        }
    }
}

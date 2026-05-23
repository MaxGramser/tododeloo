import SwiftUI

/// The right column. Shows the selected todo and edits it inline — no modal
/// sheets, the way a Mac app should feel.
struct MacInspectorView: View {
    @Bindable var board: BoardModel
    let lists: ListsModel
    @Binding var todoID: Int?

    private var todo: Todo? { board.todos.first { $0.id == todoID } }

    var body: some View {
        Group {
            if let todo {
                MacInspectorEditor(board: board, todo: todo)
                    .id(todo.id)
            } else {
                ContentUnavailableView(
                    "Geen taak geselecteerd",
                    systemImage: "sidebar.right",
                    description: Text("Kies een taak om de details te bewerken.")
                )
            }
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity)
        .background(Theme.background)
    }
}

private struct MacInspectorEditor: View {
    @Bindable var board: BoardModel
    let todo: Todo

    @State private var titleDraft: String
    @State private var noteDraft: String
    @State private var newSub = ""
    @State private var newTag = ""
    @State private var allTags: [Tag] = []
    @State private var showCustomRecurrence = false
    @FocusState private var noteFocused: Bool

    private let api = APIClient.shared

    init(board: BoardModel, todo: Todo) {
        self.board = board
        self.todo = todo
        _titleDraft = State(initialValue: todo.title)
        _noteDraft = State(initialValue: todo.description ?? "")
    }

    var body: some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 22) {
                TextField("Titel", text: $titleDraft, axis: .vertical)
                    .textFieldStyle(.plain)
                    .font(.system(size: 20, weight: .semibold))
                    .foregroundStyle(Theme.ink)
                    .onSubmit { Task { await board.rename(todo, title: titleDraft) } }

                section("Prioriteit") {
                    Picker("", selection: priorityBinding) {
                        ForEach(Priority.allCases) { Text($0.label).tag($0) }
                    }
                    .pickerStyle(.segmented)
                    .labelsHidden()
                }

                section("Herhaling") {
                    Menu(recurrenceTitle) {
                        ForEach(board.recurrencePresets) { preset in
                            Button { Task { await board.setRecurrence(todo, preset: preset.key) } } label: {
                                if todo.recurrence?.preset == preset.key {
                                    Label(preset.label, systemImage: "checkmark")
                                } else {
                                    Text(preset.label)
                                }
                            }
                        }
                        Divider()
                        Button("Aangepast…") { showCustomRecurrence = true }
                        if todo.isRecurring {
                            Divider()
                            Button("Stop herhaling", role: .destructive) { Task { await board.stopRecurrence(todo) } }
                        }
                    }
                    .menuStyle(.borderlessButton)
                    .fixedSize()
                }

                section("Notitie") {
                    TextEditor(text: $noteDraft)
                        .font(.system(size: 13))
                        .frame(minHeight: 80)
                        .scrollContentBackground(.hidden)
                        .padding(8)
                        .background(Theme.surface)
                        .clipShape(RoundedRectangle(cornerRadius: 8))
                        .focused($noteFocused)
                        .onChange(of: noteFocused) { _, focused in
                            if !focused { saveNote() }
                        }
                }

                section("Tags") { tagsView }

                VStack(alignment: .leading, spacing: 8) {
                    HStack(spacing: 8) {
                        MonoLabel("Subtaken")
                        if !(todo.subTodos ?? []).isEmpty {
                            SubProgressRing(done: todo.doneSubTodoCount, total: todo.totalSubTodoCount, size: 15)
                            MonoLabel("\(todo.doneSubTodoCount)/\(todo.totalSubTodoCount)")
                        }
                    }
                    subtasksView
                }
            }
            .padding(20)
        }
        .task { allTags = (try? await api.tags()) ?? [] }
        .sheet(isPresented: $showCustomRecurrence) {
            MacRecurrenceSheet(anchorISO: board.recurrenceAnchorISO) { rrule in
                Task { await board.setCustomRecurrence(todo, rrule: rrule) }
            }
        }
    }

    // MARK: - Tags

    @ViewBuilder
    private var tagsView: some View {
        let attached = todo.tags ?? []
        if !attached.isEmpty {
            FlowTags(tags: attached) { toggleTag($0) }
        }
        HStack(spacing: 10) {
            Menu {
                ForEach(unattachedTags) { tag in
                    Button(tag.name) { toggleTag(tag) }
                }
                if unattachedTags.isEmpty { Text("Geen andere tags") }
            } label: {
                Label("Koppelen", systemImage: "tag")
            }
            .menuStyle(.borderlessButton)
            .fixedSize()
            TextField("Nieuwe tag…", text: $newTag)
                .textFieldStyle(.roundedBorder)
                .onSubmit(createTag)
                .frame(maxWidth: 160)
        }
    }

    private var unattachedTags: [Tag] {
        let attached = Set((todo.tags ?? []).map(\.id))
        return allTags.filter { !attached.contains($0.id) }
    }

    // MARK: - Subtasks

    @ViewBuilder
    private var subtasksView: some View {
        ForEach(todo.subTodos ?? []) { sub in
            HStack(spacing: 10) {
                Button { toggleSub(sub) } label: {
                    Image(systemName: sub.isCompleted ? "checkmark.circle.fill" : "circle")
                        .foregroundStyle(sub.isCompleted ? Theme.accent : Theme.faint)
                }
                .buttonStyle(.plain)
                Text(sub.title)
                    .font(.system(size: 13))
                    .strikethrough(sub.isCompleted, color: Theme.faint)
                    .foregroundStyle(sub.isCompleted ? Theme.faint : Theme.ink)
                Spacer()
                Button { deleteSub(sub) } label: {
                    Image(systemName: "xmark").font(.system(size: 10, weight: .bold)).foregroundStyle(Theme.faint)
                }
                .buttonStyle(.plain)
            }
        }
        HStack(spacing: 8) {
            Image(systemName: "plus").font(.system(size: 12, weight: .bold)).foregroundStyle(Theme.accent)
            TextField("Subtaak toevoegen…", text: $newSub)
                .textFieldStyle(.plain)
                .font(.system(size: 13))
                .onSubmit(addSub)
        }
    }

    // MARK: - Bindings & actions

    private var priorityBinding: Binding<Priority> {
        Binding(get: { todo.priorityValue }, set: { value in Task { await board.setPriority(todo, value) } })
    }

    private var recurrenceTitle: String {
        if let summary = todo.recurrence?.summary { return summary }
        return todo.isRecurring ? "Aangepast" : "Geen herhaling"
    }

    private func saveNote() {
        guard noteDraft != (todo.description ?? "") else { return }
        Task {
            _ = try? await api.updateTodo(todo.id, description: noteDraft)
            await board.load()
        }
    }

    private func toggleTag(_ tag: Tag) {
        var ids = (todo.tags ?? []).map(\.id)
        if let index = ids.firstIndex(of: tag.id) { ids.remove(at: index) } else { ids.append(tag.id) }
        Task {
            _ = try? await api.syncTags(todo.id, tagIds: ids)
            await board.load()
        }
    }

    private func createTag() {
        let name = newTag.trimmingCharacters(in: .whitespacesAndNewlines)
        guard !name.isEmpty else { return }
        newTag = ""
        Task {
            guard let tag = try? await api.createTag(name: name) else { return }
            allTags.append(tag)
            var ids = (todo.tags ?? []).map(\.id)
            ids.append(tag.id)
            _ = try? await api.syncTags(todo.id, tagIds: ids)
            await board.load()
        }
    }

    private func addSub() {
        let title = newSub.trimmingCharacters(in: .whitespacesAndNewlines)
        guard !title.isEmpty else { return }
        newSub = ""
        Task { await board.addSubTodo(todo, title: title) }
    }

    private func toggleSub(_ sub: SubTodo) {
        Task { await board.toggleSubTodo(todo, sub) }
    }

    private func deleteSub(_ sub: SubTodo) {
        Task {
            _ = try? await api.deleteSubTodo(sub.id)
            await board.load()
        }
    }

    @ViewBuilder
    private func section<Content: View>(_ title: String, @ViewBuilder content: () -> Content) -> some View {
        VStack(alignment: .leading, spacing: 8) {
            MonoLabel(title)
            content()
        }
    }
}

/// A wrapping row of tappable tag chips (tap removes the tag from the todo).
private struct FlowTags: View {
    let tags: [Tag]
    let onTap: (Tag) -> Void

    var body: some View {
        LazyVGrid(columns: [GridItem(.adaptive(minimum: 60), spacing: 6, alignment: .leading)], alignment: .leading, spacing: 6) {
            ForEach(tags) { tag in
                Button { onTap(tag) } label: {
                    HStack(spacing: 3) {
                        TagChip(tag: tag)
                        Image(systemName: "xmark").font(.system(size: 8, weight: .bold)).foregroundStyle(Theme.faint)
                    }
                }
                .buttonStyle(.plain)
            }
        }
    }
}

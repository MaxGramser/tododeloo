import SwiftUI

/// Full editor for a single todo: title, priority, note, tags and sub-todos.
/// Everything calls the API directly and reports back to the board via
/// `onChange` so the list behind stays in sync.
struct TodoDetailView: View {
    @Environment(\.dismiss) private var dismiss
    @Environment(Session.self) private var session

    @State private var todo: Todo
    @State private var titleDraft: String
    @State private var descriptionDraft: String
    @State private var newSubTitle = ""
    @State private var newTagName = ""
    @State private var allTags: [Tag] = []
    @State private var errorMessage: String?

    let onChange: () async -> Void
    private let api = APIClient.shared

    init(todo: Todo, onChange: @escaping () async -> Void) {
        _todo = State(initialValue: todo)
        _titleDraft = State(initialValue: todo.title)
        _descriptionDraft = State(initialValue: todo.description ?? "")
        self.onChange = onChange
    }

    var body: some View {
        NavigationStack {
            ScrollView {
                VStack(alignment: .leading, spacing: 30) {
                    titleSection
                    prioritySection
                    noteSection
                    tagsSection
                    subTodosSection
                    deleteButton
                }
                .padding(24)
            }
            .background(Theme.background.ignoresSafeArea())
            .scrollDismissesKeyboard(.interactively)
            .toolbar {
                ToolbarItem(placement: .topBarTrailing) {
                    Button("Klaar") {
                        Task {
                            await persistDrafts()
                            dismiss()
                        }
                    }
                    .fontWeight(.bold)
                    .tint(Theme.ink)
                }
            }
        }
        .task { await loadTags() }
    }

    // MARK: - Sections

    private var titleSection: some View {
        VStack(alignment: .leading, spacing: 10) {
            MonoLabel("Taak")
            TextField("Titel", text: $titleDraft, axis: .vertical)
                .font(.display(26))
                .foregroundStyle(Theme.ink)
                .onSubmit { saveTitle() }
            if let errorMessage {
                Text(errorMessage)
                    .font(.system(size: 13, weight: .medium))
                    .foregroundStyle(Theme.accent)
            }
        }
    }

    private var prioritySection: some View {
        VStack(alignment: .leading, spacing: 10) {
            MonoLabel("Prioriteit")
            HStack(spacing: 8) {
                ForEach(Priority.allCases) { priority in
                    let isSelected = todo.priorityValue == priority
                    Button {
                        setPriority(priority)
                    } label: {
                        Text(priority.label)
                            .font(.system(size: 14, weight: .semibold))
                            .foregroundStyle(isSelected ? Theme.background : Theme.ink)
                            .padding(.horizontal, 16)
                            .padding(.vertical, 9)
                            .background(isSelected ? Theme.ink : Theme.surface)
                            .clipShape(Capsule())
                            .overlay(
                                Capsule().strokeBorder(Theme.hairline, lineWidth: isSelected ? 0 : 1)
                            )
                    }
                    .buttonStyle(.plain)
                }
            }
        }
    }

    private var noteSection: some View {
        VStack(alignment: .leading, spacing: 10) {
            MonoLabel("Notitie")
            TextEditor(text: $descriptionDraft)
                .font(.system(size: 16))
                .frame(minHeight: 90)
                .scrollContentBackground(.hidden)
                .padding(10)
                .background(Theme.surface)
                .overlay(
                    RoundedRectangle(cornerRadius: 8).strokeBorder(Theme.hairline, lineWidth: 1)
                )
        }
    }

    private var tagsSection: some View {
        VStack(alignment: .leading, spacing: 10) {
            MonoLabel("Tags")
            let attached = todo.tags ?? []
            if !attached.isEmpty {
                FlowChips(
                    tags: attached,
                    onTap: { toggleTag($0) },
                    onDelete: { deleteTag($0) }
                )
            }
            HStack(spacing: 10) {
                Menu {
                    ForEach(unattachedTags) { tag in
                        Button(tag.name) { toggleTag(tag) }
                    }
                    if unattachedTags.isEmpty {
                        Text("Geen andere tags")
                    }
                } label: {
                    Label("Tag koppelen", systemImage: "tag")
                        .font(.system(size: 14, weight: .semibold))
                        .foregroundStyle(Theme.ink)
                }
                Spacer()
            }
            HStack(spacing: 12) {
                Image(systemName: "plus")
                    .font(.system(size: 14, weight: .bold))
                    .foregroundStyle(Theme.accent)
                TextField("Nieuwe tag…", text: $newTagName)
                    .font(.system(size: 15, weight: .medium))
                    .foregroundStyle(Theme.ink)
                    .tint(Theme.accent)
                    .submitLabel(.done)
                    .onSubmit { createTag() }
            }
        }
    }

    private var subTodosSection: some View {
        VStack(alignment: .leading, spacing: 10) {
            let subs = todo.subTodos ?? []
            MonoLabel("Subtaken \(subs.isEmpty ? "" : "\(todo.openSubTodoCount)/\(subs.count)")")
            ForEach(subs) { sub in
                HStack(spacing: 12) {
                    Button {
                        toggleSub(sub)
                    } label: {
                        Image(systemName: sub.isCompleted ? "checkmark.circle.fill" : "circle")
                            .font(.system(size: 18))
                            .foregroundStyle(sub.isCompleted ? Theme.accent : Theme.faint)
                    }
                    .buttonStyle(.plain)
                    Text(sub.title)
                        .font(.system(size: 16))
                        .foregroundStyle(sub.isCompleted ? Theme.faint : Theme.ink)
                        .strikethrough(sub.isCompleted, color: Theme.faint)
                    Spacer()
                    Button {
                        deleteSub(sub)
                    } label: {
                        Image(systemName: "xmark")
                            .font(.system(size: 11, weight: .bold))
                            .foregroundStyle(Theme.faint)
                    }
                    .buttonStyle(.plain)
                }
                .padding(.vertical, 4)
            }
            HStack(spacing: 12) {
                Image(systemName: "plus")
                    .font(.system(size: 14, weight: .bold))
                    .foregroundStyle(Theme.accent)
                TextField("Subtaak toevoegen…", text: $newSubTitle)
                    .font(.system(size: 16))
                    .foregroundStyle(Theme.ink)
                    .tint(Theme.accent)
                    .submitLabel(.done)
                    .onSubmit { addSub() }
            }
        }
    }

    private var deleteButton: some View {
        Button(role: .destructive) {
            deleteTodo()
        } label: {
            Label("Verwijder taak", systemImage: "trash")
                .font(.system(size: 15, weight: .semibold))
                .foregroundStyle(Theme.accent)
        }
        .padding(.top, 8)
    }

    private var unattachedTags: [Tag] {
        let attachedIds = Set((todo.tags ?? []).map(\.id))
        return allTags.filter { !attachedIds.contains($0.id) }
    }

    // MARK: - Actions

    private func loadTags() async {
        allTags = (try? await api.tags()) ?? []
    }

    private func saveTitle() {
        let trimmed = titleDraft.trimmingCharacters(in: .whitespacesAndNewlines)
        guard !trimmed.isEmpty, trimmed != todo.title else { return }
        update { try await api.updateTodo(todo.id, title: trimmed) }
    }

    private func setPriority(_ priority: Priority) {
        update { try await api.updateTodo(todo.id, priority: priority.rawValue) }
    }

    private func toggleTag(_ tag: Tag) {
        var ids = (todo.tags ?? []).map(\.id)
        if let index = ids.firstIndex(of: tag.id) {
            ids.remove(at: index)
        } else {
            ids.append(tag.id)
        }
        update { try await api.syncTags(todo.id, tagIds: ids) }
    }

    private func createTag() {
        let name = newTagName.trimmingCharacters(in: .whitespacesAndNewlines)
        guard !name.isEmpty else { return }
        newTagName = ""
        Task {
            do {
                let tag = try await api.createTag(name: name)
                if !allTags.contains(where: { $0.id == tag.id }) {
                    allTags.append(tag)
                }
                var ids = (todo.tags ?? []).map(\.id)
                if !ids.contains(tag.id) {
                    ids.append(tag.id)
                }
                todo = try await api.syncTags(todo.id, tagIds: ids)
                await onChange()
            } catch {
                handle(error)
            }
        }
    }

    /// Delete a tag everywhere. The server detaches it from all todos; we drop it
    /// from this todo's chips and the available list.
    private func deleteTag(_ tag: Tag) {
        Task {
            do {
                try await api.deleteTag(tag.id)
                allTags.removeAll { $0.id == tag.id }
                if (todo.tags ?? []).contains(where: { $0.id == tag.id }) {
                    let remaining = (todo.tags ?? []).map(\.id).filter { $0 != tag.id }
                    todo = try await api.syncTags(todo.id, tagIds: remaining)
                }
                await onChange()
            } catch {
                handle(error)
            }
        }
    }

    private func addSub() {
        let title = newSubTitle.trimmingCharacters(in: .whitespacesAndNewlines)
        guard !title.isEmpty else { return }
        newSubTitle = ""
        update { try await api.createSubTodo(todoId: todo.id, title: title) }
    }

    private func toggleSub(_ sub: SubTodo) {
        update { try await api.toggleSubTodo(sub.id) }
    }

    private func deleteSub(_ sub: SubTodo) {
        update { try await api.deleteSubTodo(sub.id) }
    }

    private func deleteTodo() {
        Task {
            do {
                try await api.deleteTodo(todo.id)
                await onChange()
                dismiss()
            } catch {
                handle(error)
            }
        }
    }

    private func persistDrafts() async {
        do {
            var changed = false
            let trimmedTitle = titleDraft.trimmingCharacters(in: .whitespacesAndNewlines)
            if !trimmedTitle.isEmpty, trimmedTitle != todo.title {
                todo = try await api.updateTodo(todo.id, title: trimmedTitle)
                changed = true
            }
            if descriptionDraft != (todo.description ?? "") {
                todo = try await api.updateTodo(todo.id, description: descriptionDraft)
                changed = true
            }
            if changed {
                await onChange()
            }
        } catch {
            handle(error)
        }
    }

    private func update(_ build: @escaping () async throws -> Todo) {
        Task {
            do {
                todo = try await build()
                await onChange()
            } catch {
                handle(error)
            }
        }
    }

    private func handle(_ error: Error) {
        if case APIError.unauthorized = error {
            session.handleUnauthorized()
            dismiss()
            return
        }
        errorMessage = (error as? APIError)?.errorDescription ?? error.localizedDescription
    }
}

/// Simple wrapping row of tappable tag chips. Tap detaches the tag from the todo;
/// press-and-hold offers deleting the tag everywhere.
private struct FlowChips: View {
    let tags: [Tag]
    let onTap: (Tag) -> Void
    let onDelete: (Tag) -> Void

    var body: some View {
        LazyVGrid(columns: [GridItem(.adaptive(minimum: 70), spacing: 8, alignment: .leading)], alignment: .leading, spacing: 8) {
            ForEach(tags) { tag in
                Button {
                    onTap(tag)
                } label: {
                    HStack(spacing: 4) {
                        TagChip(tag: tag)
                        Image(systemName: "xmark")
                            .font(.system(size: 9, weight: .bold))
                            .foregroundStyle(Theme.faint)
                    }
                }
                .buttonStyle(.plain)
                .contextMenu {
                    Button { onTap(tag) } label: {
                        Label("Loskoppelen", systemImage: "minus.circle")
                    }
                    Button(role: .destructive) { onDelete(tag) } label: {
                        Label("Verwijder tag overal", systemImage: "trash")
                    }
                }
            }
        }
    }
}

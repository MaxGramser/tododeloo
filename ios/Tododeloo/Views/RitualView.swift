import SwiftUI

/// The morning ritual: pick what carries over from yesterday and from the
/// master list, jot a few fresh ones, then start the day.
struct RitualView: View {
    @Environment(Session.self) private var session
    @Bindable var model: BoardModel

    @State private var selected: Set<Int> = []
    @State private var newTitles: [String] = []
    @State private var draft = ""
    @State private var isStarting = false

    var body: some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 28) {
                headerBlock

                if !model.preScheduled.isEmpty {
                    fixedSection(title: "Al gepland", todos: model.preScheduled)
                }
                if !model.carryOverCandidates.isEmpty {
                    selectableSection(title: "Vorige werkdag", todos: model.carryOverCandidates)
                }
                if !model.earlierCandidates.isEmpty {
                    selectableSection(title: "Eerder", todos: model.earlierCandidates)
                }
                if !model.masterOpenTodos.isEmpty {
                    selectableSection(title: "Uit alles", todos: model.masterOpenTodos)
                }

                newSection
                startButton
            }
            .padding(.horizontal, 20)
            .padding(.top, 12)
            .padding(.bottom, 48)
        }
        .background(Theme.background.ignoresSafeArea())
        .task {
            model.onUnauthorized = { session.handleUnauthorized() }
            selected = Set(model.carryOverCandidates.map(\.id))
        }
    }

    private var headerBlock: some View {
        VStack(alignment: .leading, spacing: 6) {
            MonoLabel(model.dateString.isEmpty ? "Vandaag" : DateText.long(model.dateString))
            HStack(alignment: .firstTextBaseline, spacing: 8) {
                Text("Goedemorgen")
                    .font(.display(38))
                    .foregroundStyle(Theme.ink)
                AccentDot(size: 9)
            }
            Text("Kies wat vandaag meedoet.")
                .font(.system(size: 15))
                .foregroundStyle(Theme.muted)
        }
        .frame(maxWidth: .infinity, alignment: .leading)
    }

    private func fixedSection(title: String, todos: [Todo]) -> some View {
        VStack(alignment: .leading, spacing: 0) {
            MonoLabel(title).padding(.bottom, 10)
            ForEach(todos) { todo in
                HStack(spacing: 12) {
                    AccentDot(size: 6)
                    Text(todo.title)
                        .font(.system(size: 16, weight: .medium))
                        .foregroundStyle(Theme.ink)
                    Spacer()
                }
                .padding(.vertical, 10)
            }
        }
    }

    private func selectableSection(title: String, todos: [Todo]) -> some View {
        VStack(alignment: .leading, spacing: 0) {
            MonoLabel(title).padding(.bottom, 10)
            ForEach(todos) { todo in
                Button {
                    toggle(todo.id)
                } label: {
                    HStack(spacing: 12) {
                        Image(systemName: selected.contains(todo.id) ? "checkmark.square.fill" : "square")
                            .font(.system(size: 20))
                            .foregroundStyle(selected.contains(todo.id) ? Theme.accent : Theme.faint)
                        Text(todo.title)
                            .font(.system(size: 16, weight: .medium))
                            .foregroundStyle(Theme.ink)
                            .multilineTextAlignment(.leading)
                        Spacer()
                    }
                    .padding(.vertical, 10)
                    .contentShape(Rectangle())
                }
                .buttonStyle(.plain)
            }
        }
    }

    private var newSection: some View {
        VStack(alignment: .leading, spacing: 0) {
            MonoLabel("Nieuw vandaag").padding(.bottom, 10)
            ForEach(Array(newTitles.enumerated()), id: \.offset) { index, title in
                HStack(spacing: 12) {
                    AccentDot(size: 6)
                    Text(title)
                        .font(.system(size: 16, weight: .medium))
                        .foregroundStyle(Theme.ink)
                    Spacer()
                    Button {
                        newTitles.remove(at: index)
                    } label: {
                        Image(systemName: "xmark")
                            .font(.system(size: 12, weight: .bold))
                            .foregroundStyle(Theme.faint)
                    }
                }
                .padding(.vertical, 10)
            }
            HStack(spacing: 12) {
                Image(systemName: "plus")
                    .font(.system(size: 16, weight: .bold))
                    .foregroundStyle(Theme.accent)
                TextField("Iets nieuws…", text: $draft)
                    .font(.system(size: 16, weight: .medium))
                    .foregroundStyle(Theme.ink)
                    .tint(Theme.accent)
                    .submitLabel(.done)
                    .onSubmit(addDraft)
            }
            .padding(.vertical, 10)
        }
    }

    private var startButton: some View {
        Button {
            start()
        } label: {
            if isStarting {
                ProgressView().tint(Theme.background)
            } else {
                Text("Start de dag")
            }
        }
        .buttonStyle(PrimaryButtonStyle())
        .disabled(isStarting)
        .padding(.top, 8)
    }

    private func toggle(_ id: Int) {
        if selected.contains(id) {
            selected.remove(id)
        } else {
            selected.insert(id)
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
        Task {
            await model.startDay(carryOverIds: Array(selected), newTitles: newTitles)
            isStarting = false
        }
    }
}

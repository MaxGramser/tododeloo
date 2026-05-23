import SwiftUI

/// The menu-bar dropdown: today's open todos at a glance, a one-line quick-add,
/// and quick access back into the app — the TickTick-style mini list. Reuses the
/// shared BoardModel + APIClient (token from the Keychain), so it stays in sync
/// with the main window on its next load.
struct MacMenuBarContent: View {
    @State private var model = BoardModel(context: .today)

    var body: some View {
        VStack(alignment: .leading, spacing: 0) {
            header
            quickAdd
            Divider().padding(.horizontal, 14)
            body(for: phase)
            Divider().padding(.horizontal, 14)
            footer
        }
        .frame(width: 320)
        .background(Theme.background)
        .task { if !model.hasLoaded { await model.load() } }
    }

    private enum Phase { case signedOut, ritual, empty, list }

    private var phase: Phase {
        if !APIClient.shared.isAuthenticated { return .signedOut }
        if model.needsRitual { return .ritual }
        if model.openTodos.isEmpty && model.hasLoaded { return .empty }
        return .list
    }

    private var header: some View {
        HStack(alignment: .firstTextBaseline, spacing: 8) {
            Text("Vandaag").font(.display(20)).foregroundStyle(Theme.ink)
            AccentDot(size: 6)
            Spacer()
            if model.openCount > 0 {
                MonoLabel("\(model.openCount) open", color: Theme.faint)
            }
        }
        .padding(.horizontal, 16)
        .padding(.top, 14)
        .padding(.bottom, 10)
    }

    private var quickAdd: some View {
        VStack(spacing: 0) {
            HStack(spacing: 9) {
                Image(systemName: "plus").font(.system(size: 12, weight: .bold)).foregroundStyle(Theme.accent)
                TextField("Snel toevoegen…", text: $model.quickAddText)
                    .textFieldStyle(.plain)
                    .font(.system(size: 13, weight: .medium))
                    .onSubmit { Task { await model.submitQuickAdd() } }
            }
            .padding(.horizontal, 12)
            .padding(.vertical, 9)

            ParsePreviewStrip(text: model.quickAddText, style: .attached)
        }
        .background(Theme.surface)
        .clipShape(RoundedRectangle(cornerRadius: 10, style: .continuous))
        .overlay(RoundedRectangle(cornerRadius: 10, style: .continuous).strokeBorder(Theme.hairline, lineWidth: 1))
        .padding(.horizontal, 14)
        .padding(.bottom, 10)
    }

    @ViewBuilder
    private func body(for phase: Phase) -> some View {
        switch phase {
        case .signedOut:
            hint("Log in via het hoofdvenster.")
        case .ritual:
            hint("Start je dag in Tododeloo.")
        case .empty:
            hint("Niets open vandaag. 🎉")
        case .list:
            ScrollView {
                VStack(spacing: 0) {
                    ForEach(model.openTodos.prefix(8)) { todo in
                        row(todo)
                    }
                }
                .animation(.snappy(duration: 0.25), value: model.openTodos)
            }
            .frame(maxHeight: 320)
        }
    }

    private func row(_ todo: Todo) -> some View {
        HStack(spacing: 10) {
            Button { Task { await model.toggle(todo) } } label: {
                Image(systemName: todo.hasSubTodos ? "circle.dashed" : "circle")
                    .font(.system(size: 14))
                    .foregroundStyle(todo.priorityValue == .high ? Theme.accent : Theme.faint)
            }
            .buttonStyle(.plain)
            Text(todo.title)
                .font(.system(size: 13))
                .foregroundStyle(Theme.ink)
                .lineLimit(1)
            Spacer(minLength: 0)
        }
        .padding(.horizontal, 16)
        .padding(.vertical, 7)
        .contentShape(Rectangle())
        .transition(.opacity.combined(with: .move(edge: .leading)))
    }

    private func hint(_ text: String) -> some View {
        Text(text)
            .font(.system(size: 13))
            .foregroundStyle(Theme.muted)
            .frame(maxWidth: .infinity, alignment: .leading)
            .padding(.horizontal, 16)
            .padding(.vertical, 14)
    }

    private var footer: some View {
        HStack(spacing: 14) {
            Button("Open Tododeloo") {
                NSApplication.shared.activate(ignoringOtherApps: true)
                NSApplication.shared.windows.first { $0.canBecomeMain }?.makeKeyAndOrderFront(nil)
            }
            .buttonStyle(.plain)
            .foregroundStyle(Theme.ink)
            Spacer()
            if phase == .list || phase == .empty {
                Button("Ritueel opnieuw") { Task { await model.resetRitual() } }
                    .buttonStyle(.plain)
                    .foregroundStyle(Theme.muted)
            }
        }
        .font(.system(size: 12, weight: .medium))
        .padding(.horizontal, 16)
        .padding(.vertical, 10)
    }
}

import SwiftUI

/// Native macOS app. Shares the core (Models / Networking / State / Theme) with
/// the iPhone app; the UI here is built for the Mac: a three-column
/// NavigationSplitView with a sidebar, a todo list, and a detail inspector.
@main
struct TododelooMacApp: App {
    @NSApplicationDelegateAdaptor(AppDelegate.self) private var appDelegate
    @State private var session = Session()

    var body: some Scene {
        WindowGroup {
            MacRootView()
                .environment(session)
                .tint(Theme.accent)
                .frame(minWidth: 860, minHeight: 560)
        }
        .defaultSize(width: 1120, height: 720)
        .commands { BoardCommands() }

        // Menu-bar mini list: today at a glance + quick-add, without the window.
        MenuBarExtra("Tododeloo", systemImage: "checklist") {
            MacMenuBarContent()
                .environment(session)
                .tint(Theme.accent)
        }
        .menuBarExtraStyle(.window)

        Settings {
            MacSettingsView()
                .environment(session)
        }
    }
}

// MARK: - Menu bar commands

/// The actions the main menu drives. `MacContentView` publishes a fresh value
/// through `focusedSceneValue` on every render, so the menu always reflects the
/// focused window's current board and selection. It's nil before sign-in, which
/// disables the items.
struct BoardActions {
    var hasSelectedTodo = false
    var selectedIsCompleted = false
    var isDayBoard = false
    var isToday = false
    var hasUpcoming = false

    var newTodo: () -> Void = {}
    var newList: () -> Void = {}
    var toggleSelected: () -> Void = {}
    var renameSelected: () -> Void = {}
    var moveSelected: () -> Void = {}
    var deleteSelected: () -> Void = {}
    var goToday: () -> Void = {}
    var goMaster: () -> Void = {}
    var goUpcoming: () -> Void = {}
    var previousDay: () -> Void = {}
    var nextDay: () -> Void = {}
    var resetRitual: () -> Void = {}
}

struct BoardActionsKey: FocusedValueKey {
    typealias Value = BoardActions
}

extension FocusedValues {
    var boardActions: BoardActions? {
        get { self[BoardActionsKey.self] }
        set { self[BoardActionsKey.self] = newValue }
    }
}

/// The native menu bar: New items in the File menu, a "Taak" menu for the
/// selected todo, and a "Ga" menu for navigation, day paging and the ritual.
/// Every item disables itself when it doesn't apply to the focused board.
struct BoardCommands: Commands {
    @FocusedValue(\.boardActions) private var actions

    private var hasTodo: Bool { actions?.hasSelectedTodo ?? false }

    var body: some Commands {
        // Replace "New Window" — this is a single-board app — with our own items.
        CommandGroup(replacing: .newItem) {
            Button("Nieuwe taak") { actions?.newTodo() }
                .keyboardShortcut("n", modifiers: .command)
                .disabled(actions == nil)
            Button("Nieuwe lijst…") { actions?.newList() }
                .keyboardShortcut("n", modifiers: [.command, .shift])
                .disabled(actions == nil)
        }

        CommandMenu("Taak") {
            Button((actions?.selectedIsCompleted ?? false) ? "Markeer onaf" : "Markeer af") {
                actions?.toggleSelected()
            }
            .keyboardShortcut(.return, modifiers: .command)
            .disabled(!hasTodo)

            Button("Hernoem") { actions?.renameSelected() }
                .keyboardShortcut("r", modifiers: .command)
                .disabled(!hasTodo)

            Button("Verplaats naar datum…") { actions?.moveSelected() }
                .keyboardShortcut("d", modifiers: .command)
                .disabled(!hasTodo)

            Divider()

            Button("Verwijder") { actions?.deleteSelected() }
                .keyboardShortcut(.delete, modifiers: .command)
                .disabled(!hasTodo)
        }

        CommandMenu("Ga") {
            Button("Vandaag") { actions?.goToday() }
                .keyboardShortcut("1", modifiers: .command)
                .disabled(actions == nil)
            Button("Alles") { actions?.goMaster() }
                .keyboardShortcut("2", modifiers: .command)
                .disabled(actions == nil)
            Button("Binnenkort") { actions?.goUpcoming() }
                .keyboardShortcut("3", modifiers: .command)
                .disabled(!(actions?.hasUpcoming ?? false))

            Divider()

            Button("Vorige dag") { actions?.previousDay() }
                .keyboardShortcut("[", modifiers: .command)
                .disabled(!(actions?.isDayBoard ?? false))
            Button("Volgende dag") { actions?.nextDay() }
                .keyboardShortcut("]", modifiers: .command)
                .disabled(!(actions?.isDayBoard ?? false))

            Divider()

            Button("Ritueel opnieuw") { actions?.resetRitual() }
                .keyboardShortcut("r", modifiers: [.command, .shift])
                .disabled(!(actions?.isToday ?? false))
        }
    }
}

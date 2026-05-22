import AppIntents

/// Registers the spoken phrases for Siri. The phrases must contain
/// `\(.applicationName)` — which resolves to "Tododeloo". iOS does not allow
/// free-form text inside the trigger phrase itself (a phrase parameter must be
/// an AppEntity/AppEnum), so the phrase triggers the intent and Siri then asks
/// what to add. For a fully one-shot custom phrase, build a shortcut in the
/// Shortcuts app with a "Dictate Text" step feeding QuickAddTodoIntent.
struct TododelooShortcuts: AppShortcutsProvider {
    static var appShortcuts: [AppShortcut] {
        AppShortcut(
            intent: QuickAddTodoIntent(),
            phrases: [
                "Voeg toe aan \(.applicationName)",
                "\(.applicationName) toevoegen",
                "Noteer in \(.applicationName)",
                "Nieuwe taak in \(.applicationName)",
            ],
            shortTitle: "Snel toevoegen",
            systemImageName: "plus.circle.fill"
        )
    }

    static var shortcutTileColor: ShortcutTileColor = .orange
}

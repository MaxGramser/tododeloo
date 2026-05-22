import AppIntents
import Foundation

/// Siri / Shortcuts entry point for quick-adding a todo. Runs in the background
/// (no app launch), reads the shared token from the Keychain, and posts to the
/// same `/api/quick-add` endpoint the app uses — so it lands on today (or the
/// next workday on a weekend) and on the master list.
struct QuickAddTodoIntent: AppIntent {
    static var title: LocalizedStringResource = "Voeg toe aan Tododeloo"

    static var description = IntentDescription(
        "Voegt een taak toe aan je lijst voor vandaag."
    )

    /// Keep it conversational: don't open the app for a quick capture.
    static var openAppWhenRun: Bool = false

    @Parameter(title: "Taak", requestValueDialog: "Wat wil je toevoegen?")
    var taskTitle: String

    static var parameterSummary: some ParameterSummary {
        Summary("Voeg \(\.$taskTitle) toe aan Tododeloo")
    }

    @MainActor
    func perform() async throws -> some IntentResult & ProvidesDialog {
        let trimmed = taskTitle.trimmingCharacters(in: .whitespacesAndNewlines)

        guard !trimmed.isEmpty else {
            throw $taskTitle.needsValueError("Wat wil je toevoegen?")
        }

        guard APIClient.shared.isAuthenticated else {
            return .result(dialog: "Log eerst in op Tododeloo.")
        }

        do {
            let response = try await APIClient.shared.quickAdd(title: trimmed)
            return .result(dialog: IntentDialog(stringLiteral: confirmation(for: trimmed, on: response.targetDate)))
        } catch {
            let reason = (error as? APIError)?.errorDescription ?? "Er ging iets mis."
            return .result(dialog: IntentDialog(stringLiteral: reason))
        }
    }

    private func confirmation(for title: String, on targetDate: String?) -> String {
        guard let targetDate else {
            return "Toegevoegd: \(title)."
        }
        if targetDate == DateText.ymd(Date()) {
            return "Toegevoegd voor vandaag: \(title)."
        }
        return "Ingepland voor \(DateText.long(targetDate)): \(title)."
    }
}

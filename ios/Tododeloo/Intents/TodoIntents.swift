import AppIntents
import Foundation

/// A todo exposed to Siri / Shortcuts / Spotlight as a first-class object, so a
/// shortcut can pick "which task" to act on. Backed by today's open todos read
/// straight from the API (the token is shared via the Keychain).
struct TodoEntity: AppEntity {
    let id: Int
    let title: String

    static var typeDisplayRepresentation: TypeDisplayRepresentation = "Taak"

    var displayRepresentation: DisplayRepresentation {
        DisplayRepresentation(title: "\(title)")
    }

    static var defaultQuery = TodoEntityQuery()
}

struct TodoEntityQuery: EntityQuery {
    func entities(for identifiers: [Int]) async throws -> [TodoEntity] {
        let todos = try await Self.openTodayTodos()
        return todos
            .filter { identifiers.contains($0.id) }
            .map { TodoEntity(id: $0.id, title: $0.title) }
    }

    func suggestedEntities() async throws -> [TodoEntity] {
        try await Self.openTodayTodos().map { TodoEntity(id: $0.id, title: $0.title) }
    }

    private static func openTodayTodos() async throws -> [Todo] {
        guard APIClient.shared.isAuthenticated else { return [] }
        let response = try await APIClient.shared.today()
        return (response.list?.todos ?? []).filter { !$0.isCompleted }
    }
}

/// "Markeer een taak af" — completes a chosen todo without opening the app.
struct CompleteTodoIntent: AppIntent {
    static var title: LocalizedStringResource = "Markeer taak af"
    static var description = IntentDescription("Markeert een taak van vandaag als afgerond.")
    static var openAppWhenRun: Bool = false

    @Parameter(title: "Taak", requestValueDialog: "Welke taak is af?")
    var todo: TodoEntity

    static var parameterSummary: some ParameterSummary {
        Summary("Markeer \(\.$todo) af")
    }

    @MainActor
    func perform() async throws -> some IntentResult & ProvidesDialog {
        guard APIClient.shared.isAuthenticated else {
            return .result(dialog: "Log eerst in op Tododeloo.")
        }
        do {
            let updated = try await APIClient.shared.complete(todo.id)
            return .result(dialog: "Afgerond: \(updated.title).")
        } catch {
            let reason = (error as? APIError)?.errorDescription ?? "Er ging iets mis."
            return .result(dialog: IntentDialog(stringLiteral: reason))
        }
    }
}

/// "Wat staat er vandaag?" — a spoken/Shortcuts summary of today's open todos.
struct TodaySummaryIntent: AppIntent {
    static var title: LocalizedStringResource = "Wat staat er vandaag?"
    static var description = IntentDescription("Vertelt hoeveel taken je vandaag nog open hebt.")
    static var openAppWhenRun: Bool = false

    @MainActor
    func perform() async throws -> some IntentResult & ProvidesDialog & ReturnsValue<Int> {
        guard APIClient.shared.isAuthenticated else {
            return .result(value: 0, dialog: "Log eerst in op Tododeloo.")
        }
        do {
            let response = try await APIClient.shared.today()
            let open = (response.list?.todos ?? []).filter { !$0.isCompleted }

            guard !open.isEmpty else {
                return .result(value: 0, dialog: "Je hebt vandaag geen open taken meer.")
            }

            let titles = open.prefix(3).map(\.title).joined(separator: ", ")
            let extra = open.count > 3 ? " en nog \(open.count - 3) meer" : ""
            let count = open.count == 1 ? "1 taak" : "\(open.count) taken"
            return .result(value: open.count, dialog: "Je hebt vandaag \(count): \(titles)\(extra).")
        } catch {
            let reason = (error as? APIError)?.errorDescription ?? "Er ging iets mis."
            return .result(value: 0, dialog: IntentDialog(stringLiteral: reason))
        }
    }
}

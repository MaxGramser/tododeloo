import Foundation

enum APIError: LocalizedError {
    case notLoggedIn
    case unauthorized
    case validation([String])
    case server(Int, String?)
    case network(String)
    case decoding(String)

    var errorDescription: String? {
        switch self {
        case .notLoggedIn:
            return "Log eerst in op Tododeloo."
        case .unauthorized:
            return "Je sessie is verlopen. Log opnieuw in."
        case .validation(let messages):
            return messages.first ?? "Controleer je invoer."
        case .server(let code, let message):
            return message ?? "Serverfout (\(code))."
        case .network(let message):
            return message
        case .decoding(let message):
            return "Onverwacht antwoord van de server. \(message)"
        }
    }
}

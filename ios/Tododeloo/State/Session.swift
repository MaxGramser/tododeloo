import Observation
import SwiftUI
#if canImport(UIKit)
import UIKit
#endif

/// Authentication state for the app. Owns the current user and drives the
/// root view between the login screen and the main UI.
@MainActor
@Observable
final class Session {
    enum State {
        case loading
        case signedOut
        case signedIn
    }

    var state: State = .loading
    var user: User?
    var loginError: String?
    var isWorking = false

    private let api = APIClient.shared

    /// Decide where to start: validate an existing token, otherwise show login.
    func bootstrap() async {
        guard api.isAuthenticated else {
            state = .signedOut
            return
        }
        do {
            user = try await api.me()
            state = .signedIn
        } catch APIError.unauthorized {
            Keychain.token = nil
            state = .signedOut
        } catch {
            // Network hiccup with a stored token: let the user in optimistically.
            state = .signedIn
        }
    }

    func login(email: String, password: String) async {
        isWorking = true
        loginError = nil
        defer { isWorking = false }
        do {
            let response = try await api.login(
                email: email.trimmingCharacters(in: .whitespacesAndNewlines),
                password: password,
                deviceName: Self.deviceName
            )
            user = response.user
            state = .signedIn
        } catch {
            loginError = (error as? APIError)?.errorDescription ?? error.localizedDescription
        }
    }

    func logout() async {
        await api.logout()
        user = nil
        state = .signedOut
    }

    /// Called by feature models when the API reports a 401.
    func handleUnauthorized() {
        Keychain.token = nil
        user = nil
        state = .signedOut
    }

    static var deviceName: String {
        #if os(macOS)
        return Host.current().localizedName ?? "Mac"
        #elseif targetEnvironment(macCatalyst)
        return "Mac"
        #else
        return UIDevice.current.name
        #endif
    }
}

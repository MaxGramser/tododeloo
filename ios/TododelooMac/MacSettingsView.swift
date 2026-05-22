import SwiftUI

/// The standard macOS Settings window: account info, server URL, log out.
struct MacSettingsView: View {
    @Environment(Session.self) private var session
    @State private var serverURL = AppConfig.apiBaseURL.absoluteString

    var body: some View {
        Form {
            Section("Account") {
                LabeledContent("Naam", value: session.user?.name ?? "—")
                LabeledContent("E-mail", value: session.user?.email ?? "—")
            }
            Section("Server") {
                TextField("API-URL", text: $serverURL)
                    .onSubmit { AppConfig.setBaseURL(serverURL) }
                Button("Bewaren") { AppConfig.setBaseURL(serverURL) }
            }
            Section {
                Button("Uitloggen", role: .destructive) {
                    AppConfig.setBaseURL(serverURL)
                    Task { await session.logout() }
                }
            }
        }
        .formStyle(.grouped)
        .frame(width: 440, height: 320)
    }
}

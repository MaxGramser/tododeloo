import SwiftUI

/// A small, centered login form. Password autofill works on macOS via the
/// standard text content types.
struct MacLoginView: View {
    @Environment(Session.self) private var session
    @State private var email = ""
    @State private var password = ""

    var body: some View {
        VStack(alignment: .leading, spacing: 22) {
            HStack(spacing: 8) {
                Text("Tododeloo")
                    .font(.display(34))
                    .foregroundStyle(Theme.ink)
                AccentDot(size: 9)
            }
            Text("Log in om te beginnen.")
                .font(.system(size: 15))
                .foregroundStyle(Theme.muted)

            VStack(spacing: 12) {
                TextField("E-mail", text: $email)
                    .textContentType(.username)
                SecureField("Wachtwoord", text: $password)
                    .textContentType(.password)
                    .onSubmit(login)
            }
            .textFieldStyle(.roundedBorder)
            .frame(width: 300)

            if let error = session.loginError {
                Text(error)
                    .font(.callout)
                    .foregroundStyle(Theme.accent)
                    .frame(width: 300, alignment: .leading)
            }

            Button(action: login) {
                Text(session.isWorking ? "Bezig…" : "Inloggen")
                    .frame(width: 300)
                    .padding(.vertical, 4)
            }
            .controlSize(.large)
            .buttonStyle(.borderedProminent)
            .tint(Theme.ink)
            .keyboardShortcut(.return)
            .disabled(session.isWorking || email.isEmpty || password.isEmpty)
        }
        .padding(48)
        .frame(maxWidth: .infinity, maxHeight: .infinity)
        .background(Theme.background)
    }

    private func login() {
        guard !email.isEmpty, !password.isEmpty else { return }
        Task { await session.login(email: email, password: password) }
    }
}

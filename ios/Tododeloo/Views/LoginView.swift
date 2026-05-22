import SwiftUI

struct LoginView: View {
    @Environment(Session.self) private var session
    @State private var email = ""
    @State private var password = ""
    @State private var showServerSheet = false
    @FocusState private var focus: Field?

    private enum Field {
        case email
        case password
    }

    var body: some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 0) {
                Spacer(minLength: 80)

                VStack(alignment: .leading, spacing: 10) {
                    MonoLabel("Tododeloo")
                    HStack(alignment: .firstTextBaseline, spacing: 10) {
                        Text("Inloggen")
                            .font(.display(46))
                            .foregroundStyle(Theme.ink)
                        AccentDot(size: 11)
                    }
                }

                VStack(alignment: .leading, spacing: 28) {
                    UnderlinedField(placeholder: "E-mail", text: $email, submitLabel: .next) {
                        focus = .password
                    }
                    .keyboardType(.emailAddress)
                    .textContentType(.username)
                    .focused($focus, equals: .email)

                    UnderlinedField(placeholder: "Wachtwoord", text: $password, isSecure: true, submitLabel: .go) {
                        submit()
                    }
                    .textContentType(.password)
                    .focused($focus, equals: .password)
                }
                .padding(.top, 48)

                if let error = session.loginError {
                    Text(error)
                        .font(.system(size: 14, weight: .medium))
                        .foregroundStyle(Theme.accent)
                        .padding(.top, 20)
                }

                Button(action: submit) {
                    if session.isWorking {
                        ProgressView().tint(Theme.background)
                    } else {
                        Text("Log in")
                    }
                }
                .buttonStyle(PrimaryButtonStyle())
                .disabled(session.isWorking || email.isEmpty || password.isEmpty)
                .opacity(email.isEmpty || password.isEmpty ? 0.4 : 1)
                .padding(.top, 36)

                Button {
                    showServerSheet = true
                } label: {
                    MonoLabel("Server instellen", color: Theme.faint)
                }
                .padding(.top, 20)

                Spacer(minLength: 40)
            }
            .padding(.horizontal, 28)
            .frame(maxWidth: 520)
            .frame(maxWidth: .infinity)
        }
        .background(Theme.background.ignoresSafeArea())
        .scrollDismissesKeyboard(.interactively)
        .sheet(isPresented: $showServerSheet) {
            ServerSheet()
        }
    }

    private func submit() {
        guard !email.isEmpty, !password.isEmpty else { return }
        focus = nil
        Task { await session.login(email: email, password: password) }
    }
}

/// Lets the user point the app at a different backend (local Herd or a deployed
/// instance) before logging in.
struct ServerSheet: View {
    @Environment(\.dismiss) private var dismiss
    @State private var url = AppConfig.apiBaseURL.absoluteString

    var body: some View {
        SheetScaffold(title: "Server") {
            VStack(alignment: .leading, spacing: 8) {
                UnderlinedField(placeholder: "https://tododeloo.test", text: $url)
                Text("De API draait op deze host onder /api.")
                    .font(.system(size: 13))
                    .foregroundStyle(Theme.muted)
            }
            Button("Bewaren") {
                AppConfig.setBaseURL(url)
                dismiss()
            }
            .buttonStyle(PrimaryButtonStyle())
        }
        .presentationDetents([.height(260)])
    }
}

import SwiftUI

struct MainView: View {
    @Environment(Session.self) private var session
    @State private var todayModel = BoardModel(context: .today)
    @State private var showSettings = false

    var body: some View {
        TabView {
            NavigationStack {
                todayContent
                    .navigationBarTitleDisplayMode(.inline)
                    .toolbar { settingsToolbar }
            }
            .tabItem { Label("Vandaag", systemImage: "sun.max") }

            NavigationStack {
                ListsView()
                    .navigationBarTitleDisplayMode(.inline)
                    .toolbar { settingsToolbar }
            }
            .tabItem { Label("Lijsten", systemImage: "square.stack") }
        }
        .tint(Theme.accent)
        .sheet(isPresented: $showSettings) {
            SettingsView()
        }
    }

    @ViewBuilder
    private var todayContent: some View {
        if todayModel.needsRitual {
            RitualView(model: todayModel)
        } else {
            BoardView(model: todayModel)
        }
    }

    @ToolbarContentBuilder
    private var settingsToolbar: some ToolbarContent {
        ToolbarItem(placement: .topBarTrailing) {
            Button {
                showSettings = true
            } label: {
                Image(systemName: "gearshape")
                    .foregroundStyle(Theme.ink)
            }
        }
    }
}

struct SettingsView: View {
    @Environment(\.dismiss) private var dismiss
    @Environment(Session.self) private var session
    @State private var serverURL = AppConfig.apiBaseURL.absoluteString
    @State private var showSiri = false

    var body: some View {
        SheetScaffold(title: "Account") {
            VStack(alignment: .leading, spacing: 4) {
                Text(session.user?.name ?? "—")
                    .font(.display(24))
                    .foregroundStyle(Theme.ink)
                Text(session.user?.email ?? "")
                    .font(.system(size: 14))
                    .foregroundStyle(Theme.muted)
            }

            VStack(alignment: .leading, spacing: 8) {
                MonoLabel("Server")
                UnderlinedField(placeholder: "https://tododeloo.test", text: $serverURL) {
                    AppConfig.setBaseURL(serverURL)
                }
            }

            Button {
                showSiri = true
            } label: {
                HStack(spacing: 12) {
                    VStack(alignment: .leading, spacing: 6) {
                        MonoLabel("Siri", color: Theme.accent)
                        Text("Taken toevoegen met je stem")
                            .font(.system(size: 15, weight: .medium))
                            .foregroundStyle(Theme.ink)
                    }
                    Spacer()
                    Image(systemName: "chevron.right")
                        .font(.system(size: 13, weight: .bold))
                        .foregroundStyle(Theme.faint)
                }
                .contentShape(Rectangle())
            }
            .buttonStyle(.plain)

            Button {
                AppConfig.setBaseURL(serverURL)
                Task { await session.logout() }
            } label: {
                Text("Uitloggen")
            }
            .buttonStyle(PrimaryButtonStyle())
        }
        .presentationDetents([.medium, .large])
        .sheet(isPresented: $showSiri) {
            SiriHelpView()
        }
    }
}

import SwiftUI

struct RootView: View {
    @Environment(Session.self) private var session

    var body: some View {
        ZStack {
            Theme.background.ignoresSafeArea()

            switch session.state {
            case .loading:
                ProgressView()
                    .tint(Theme.ink)
            case .signedOut:
                LoginView()
                    .transition(.opacity)
            case .signedIn:
                MainView()
                    .transition(.opacity)
            }
        }
        .toastHost()
        .animation(.easeInOut(duration: 0.25), value: stateKey)
        .task {
            if session.state == .loading {
                await session.bootstrap()
            }
        }
    }

    private var stateKey: Int {
        switch session.state {
        case .loading: return 0
        case .signedOut: return 1
        case .signedIn: return 2
        }
    }
}

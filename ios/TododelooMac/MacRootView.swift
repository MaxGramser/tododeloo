import SwiftUI

/// Routes between loading, the login screen, and the main window depending on
/// the shared `Session` state — the macOS counterpart of the iOS RootView.
struct MacRootView: View {
    @Environment(Session.self) private var session

    var body: some View {
        Group {
            switch session.state {
            case .loading:
                ProgressView()
                    .controlSize(.large)
                    .frame(maxWidth: .infinity, maxHeight: .infinity)
                    .background(Theme.background)
            case .signedOut:
                MacLoginView()
            case .signedIn:
                MacContentView()
            }
        }
        .task {
            if session.state == .loading {
                await session.bootstrap()
            }
        }
    }
}

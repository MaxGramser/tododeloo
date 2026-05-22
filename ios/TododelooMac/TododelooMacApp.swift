import SwiftUI

/// Native macOS app. Shares the core (Models / Networking / State / Theme) with
/// the iPhone app; the UI here is built for the Mac: a three-column
/// NavigationSplitView with a sidebar, a todo list, and a detail inspector.
@main
struct TododelooMacApp: App {
    @State private var session = Session()

    var body: some Scene {
        WindowGroup {
            MacRootView()
                .environment(session)
                .tint(Theme.accent)
                .frame(minWidth: 860, minHeight: 560)
        }
        .defaultSize(width: 1120, height: 720)

        Settings {
            MacSettingsView()
                .environment(session)
        }
    }
}

import SwiftUI

@main
struct DisqueriaAppApp: App {
    @StateObject private var appState = AppState()

    var body: some Scene {
        WindowGroup {
            RootView()
                .environmentObject(appState)
                .preferredColorScheme(.light)
                .onOpenURL { url in
                    appState.handleAuthorizationCallback(url)
                }
        }
    }
}

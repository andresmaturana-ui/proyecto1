import SwiftUI

/// Top-level shell: a store status bar (logo, name, connection dot, Ajustes/Salir)
/// above whichever screen `AppState.screen` currently points to — the native
/// equivalent of the single `<x-dc>` component tree in the web app.
struct RootView: View {
    @EnvironmentObject var app: AppState

    var body: some View {
        VStack(spacing: 0) {
            topBar
            Group {
                switch app.screen {
                case .login: LoginView()
                case .settings: SettingsView()
                case .scan: ScanView()
                case .match: MatchResultsView()
                case .newUsed: NewUsedView()
                case .photos: PhotosView()
                case .condition: ConditionView()
                case .details: DetailsView()
                case .price: PriceView()
                case .done: DoneView()
                case .list: ProductListView()
                case .editProduct: EditProductView()
                }
            }
            .frame(maxWidth: .infinity, maxHeight: .infinity)
        }
        .background(Theme.card)
        .onAppear { app.bootstrap() }
    }

    private var topBar: some View {
        HStack {
            HStack(spacing: 10) {
                if !app.siteLogo.isEmpty {
                    RemoteThumb(url: app.siteLogo, size: 26, cornerRadius: 7)
                } else {
                    Text(app.storeInitial)
                        .font(.system(size: 13, weight: .bold))
                        .foregroundStyle(.white)
                        .frame(width: 26, height: 26)
                        .background(Theme.ink)
                        .clipShape(RoundedRectangle(cornerRadius: 7))
                }
                Text(app.storeLabel)
                    .font(.system(size: 13, weight: .bold))
                    .lineLimit(1)
                Circle()
                    .fill(app.isLoggedIn ? Theme.successGreen : Color.red)
                    .frame(width: 7, height: 7)
            }
            Spacer()
            if app.isLoggedIn {
                HStack(spacing: 14) {
                    Button("Ajustes") { app.screen = .settings }
                    Button("Salir") { app.logout() }
                }
                .font(.system(size: 12.5, weight: .medium))
                .foregroundStyle(Theme.ink)
            }
        }
        .padding(.horizontal, 18)
        .padding(.vertical, 10)
        .background(Color.white)
        .overlay(Rectangle().frame(height: 1).foregroundStyle(Color.black.opacity(0.15)), alignment: .bottom)
    }
}

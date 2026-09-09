import SwiftUI

struct DoneView: View {
    @EnvironmentObject var app: AppState

    var body: some View {
        VStack(spacing: 16) {
            Spacer()
            Image(systemName: "checkmark")
                .font(.system(size: 24, weight: .bold))
                .foregroundStyle(.white)
                .frame(width: 52, height: 52)
                .background(Theme.successGreen)
                .clipShape(Circle())
            Text("Producto creado").font(.system(size: 20, weight: .bold)).multilineTextAlignment(.center)
            Text(app.doneMsg)
                .font(.system(size: 13.5)).foregroundStyle(Color.black.opacity(0.6))
                .multilineTextAlignment(.center)
                .padding(.horizontal, 8)
            PrimaryButton(title: "Escanear otro") { app.resetScan() }
            Spacer()
        }
        .padding(30)
    }
}

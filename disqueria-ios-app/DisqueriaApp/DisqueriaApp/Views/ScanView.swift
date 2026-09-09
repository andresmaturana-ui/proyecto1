import SwiftUI

struct ScanView: View {
    @EnvironmentObject var app: AppState
    @State private var cameraError: String?

    var body: some View {
        VStack(alignment: .leading, spacing: 0) {
            HStack {
                Text("Escanear").font(.system(size: 20, weight: .bold))
                Spacer()
                Text("PASO 1/6").font(.system(size: 11, weight: .medium, design: .monospaced)).foregroundStyle(Color.black.opacity(0.5))
            }
            .padding(.horizontal, 20).padding(.top, 16).padding(.bottom, 4)

            Text("Escanea el código de barras que está en la carátula o contraportada del disco, o ingresa el número manualmente abajo.")
                .font(.system(size: 13)).foregroundStyle(Theme.subtleText)
                .padding(.horizontal, 20).padding(.bottom, 4)

            ScrollView {
                VStack(spacing: 12) {
                    if app.barcodeCameraActive {
                        ZStack(alignment: .bottomTrailing) {
                            BarcodeScannerView(
                                onDetected: { app.onBarcodeScanned($0) },
                                onError: { cameraError = $0; app.barcodeCameraActive = false }
                            )
                            .frame(height: 220)
                            .clipShape(RoundedRectangle(cornerRadius: 12))

                            Button("Cerrar cámara") { app.barcodeCameraActive = false }
                                .font(.system(size: 12, weight: .medium))
                                .foregroundStyle(.white)
                                .padding(.horizontal, 12).padding(.vertical, 8)
                                .background(Color.black.opacity(0.5))
                                .clipShape(RoundedRectangle(cornerRadius: 8))
                                .padding(10)
                        }
                    } else {
                        SecondaryButton(title: "Escanear con cámara") { app.barcodeCameraActive = true }
                    }

                    if let cameraError {
                        ErrorBanner(message: cameraError)
                    }

                    LabeledTextField(placeholder: "Código de barras (EAN/UPC)", text: $app.barcodeInput, keyboard: .numberPad, monospaced: true, autocapitalization: .never)

                    if !app.lookupError.isEmpty {
                        ErrorBanner(message: app.lookupError)
                    }

                    PrimaryButton(title: app.lookupBusy ? "Buscando en Discogs…" : "Buscar en Discogs", disabled: app.lookupBusy) {
                        Task { await app.lookup() }
                    }

                    Button(action: app.goList) {
                        HStack {
                            Text("Ver mis productos publicados")
                                .font(.system(size: 12, design: .monospaced)).foregroundStyle(Color.black.opacity(0.6))
                            Spacer()
                            Text("abrir ›").font(.system(size: 12, weight: .medium)).foregroundStyle(Theme.ink)
                        }
                        .padding(11)
                        .background(Color.white)
                        .clipShape(RoundedRectangle(cornerRadius: 10))
                    }
                    .padding(.top, 4)
                }
                .padding(.horizontal, 20)
                .padding(.bottom, 20)
            }
        }
    }
}

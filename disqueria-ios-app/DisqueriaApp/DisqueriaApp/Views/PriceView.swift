import SwiftUI

struct PriceView: View {
    @EnvironmentObject var app: AppState

    var body: some View {
        VStack(spacing: 0) {
            StepHeader(title: "Precio y publicar", step: "6/6", onBack: { app.screen = .details })
            ScrollView {
                VStack(alignment: .leading, spacing: 16) {
                    Text("Ingresa el precio de venta y cuántas unidades tienes disponibles, y publica el producto en tu tienda.")
                        .font(.system(size: 13)).foregroundStyle(Theme.subtleText)

                    HStack(spacing: 8) {
                        Text("$").font(.system(size: 22, weight: .semibold)).foregroundStyle(Color.black.opacity(0.35))
                        TextField("0", text: $app.price)
                            .keyboardType(.numberPad)
                            .font(.system(size: 24, weight: .bold))
                            .onChange(of: app.price) { app.price = $0.filter(\.isNumber) }
                    }
                    .padding(.horizontal, 14).padding(.vertical, 4)
                    .overlay(RoundedRectangle(cornerRadius: 11).stroke(Theme.ink, lineWidth: 1.5))

                    VStack(alignment: .leading, spacing: 6) {
                        Text("Cantidad en stock").font(.system(size: 12.5, weight: .semibold)).foregroundStyle(Color.black.opacity(0.6))
                        LabeledTextField(placeholder: "Ej. 1", text: $app.stock, keyboard: .numberPad)
                            .onChange(of: app.stock) { app.stock = $0.filter(\.isNumber) }
                    }

                    if !app.publishError.isEmpty {
                        ErrorBanner(message: app.publishError)
                    }
                }
                .padding(.horizontal, 20)
                .padding(.top, 6)
                .padding(.bottom, 16)
            }

            VStack {
                PrimaryButton(title: app.publishBusy ? "Publicando…" : "Publicar en mi tienda", disabled: app.publishBusy) {
                    Task { await app.publish() }
                }
            }
            .padding(.horizontal, 20).padding(.vertical, 12)
            .background(Color.white)
            .overlay(Rectangle().frame(height: 1).foregroundStyle(Color.black.opacity(0.12)), alignment: .top)
        }
    }
}

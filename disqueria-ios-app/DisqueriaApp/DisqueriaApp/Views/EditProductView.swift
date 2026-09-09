import SwiftUI

struct EditProductView: View {
    @EnvironmentObject var app: AppState

    var body: some View {
        VStack(spacing: 0) {
            StepHeader(title: "Editar producto", onBack: { app.cancelEdit() })
            ScrollView {
                VStack(alignment: .leading, spacing: 16) {
                    VStack(alignment: .leading, spacing: 6) {
                        Text("Nombre").font(.system(size: 12.5, weight: .semibold)).foregroundStyle(Color.black.opacity(0.6))
                        LabeledTextField(placeholder: "", text: $app.editTitle)
                    }

                    HStack(spacing: 8) {
                        Text("$").font(.system(size: 22, weight: .semibold)).foregroundStyle(Color.black.opacity(0.35))
                        TextField("0", text: $app.editPrice)
                            .keyboardType(.numberPad)
                            .font(.system(size: 24, weight: .bold))
                            .onChange(of: app.editPrice) { app.editPrice = $0.filter(\.isNumber) }
                    }
                    .padding(.horizontal, 14).padding(.vertical, 4)
                    .overlay(RoundedRectangle(cornerRadius: 11).stroke(Theme.ink, lineWidth: 1.5))

                    VStack(alignment: .leading, spacing: 6) {
                        Text("Cantidad en stock").font(.system(size: 12.5, weight: .semibold)).foregroundStyle(Color.black.opacity(0.6))
                        LabeledTextField(placeholder: "Ej. 1", text: $app.editStock, keyboard: .numberPad)
                            .onChange(of: app.editStock) { app.editStock = $0.filter(\.isNumber) }
                    }

                    if !app.editError.isEmpty {
                        ErrorBanner(message: app.editError)
                    }
                }
                .padding(.horizontal, 20)
                .padding(.top, 6)
                .padding(.bottom, 16)
            }

            VStack {
                PrimaryButton(title: app.editBusy ? "Guardando…" : "Guardar cambios", disabled: app.editBusy) {
                    Task { await app.saveEdit() }
                }
            }
            .padding(.horizontal, 20).padding(.vertical, 12)
            .background(Color.white)
            .overlay(Rectangle().frame(height: 1).foregroundStyle(Color.black.opacity(0.12)), alignment: .top)
        }
    }
}

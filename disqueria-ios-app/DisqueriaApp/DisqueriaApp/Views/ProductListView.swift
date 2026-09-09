import SwiftUI

struct ProductListView: View {
    @EnvironmentObject var app: AppState
    @State private var pendingDelete: WooProductSummary?

    var body: some View {
        VStack(spacing: 0) {
            StepHeader(title: "Mis productos", onBack: { app.screen = .scan })

            if !app.listError.isEmpty {
                ErrorBanner(message: app.listError)
                    .padding(.horizontal, 20)
            }

            ScrollView {
                VStack(spacing: 9) {
                    ForEach(app.products) { p in
                        VStack(spacing: 8) {
                            HStack(spacing: 10) {
                                RemoteThumb(url: p.featuredImage, size: 40, cornerRadius: 6)
                                Text(p.name ?? "(sin título)")
                                    .font(.system(size: 13, weight: .medium))
                                    .lineLimit(1)
                                Spacer()
                                Text((p.regularPrice?.isEmpty == false) ? "$\(p.regularPrice!)" : "—")
                                    .font(.system(size: 13, weight: .semibold))
                            }
                            HStack(spacing: 8) {
                                Button("Editar") { app.startEdit(p) }
                                    .font(.system(size: 12, weight: .semibold))
                                    .frame(maxWidth: .infinity).padding(8)
                                    .foregroundStyle(Theme.ink)
                                    .overlay(RoundedRectangle(cornerRadius: 8).stroke(Color.black.opacity(0.2), lineWidth: 1))
                                Button("Eliminar") { pendingDelete = p }
                                    .font(.system(size: 12, weight: .semibold))
                                    .frame(maxWidth: .infinity).padding(8)
                                    .foregroundStyle(Theme.errorFg)
                                    .overlay(RoundedRectangle(cornerRadius: 8).stroke(Color(red: 1, green: 0.7, blue: 0.7), lineWidth: 1))
                            }
                        }
                        .padding(12)
                        .background(Color.white)
                        .clipShape(RoundedRectangle(cornerRadius: 10))
                    }
                }
                .padding(.horizontal, 20)
                .padding(.vertical, 9)
            }
        }
        .alert(item: $pendingDelete) { product in
            Alert(
                title: Text("¿Eliminar \"\(product.name ?? "")\"?"),
                message: Text("Esta acción no se puede deshacer."),
                primaryButton: .destructive(Text("Eliminar")) {
                    Task { await app.deleteProduct(product) }
                },
                secondaryButton: .cancel(Text("Cancelar"))
            )
        }
    }
}

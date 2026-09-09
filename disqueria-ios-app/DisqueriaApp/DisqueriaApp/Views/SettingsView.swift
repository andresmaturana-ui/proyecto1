import SwiftUI

struct SettingsView: View {
    @EnvironmentObject var app: AppState

    var body: some View {
        VStack(spacing: 0) {
            StepHeader(title: "Ajustes", onBack: { app.saveSettings(); app.screen = .scan })
            ScrollView {
                VStack(alignment: .leading, spacing: 18) {
                    VStack(alignment: .leading, spacing: 8) {
                        Text("Categoría de WooCommerce").font(.system(size: 13, weight: .semibold))
                        LabeledTextField(placeholder: "ID de categoría (ej. 12)", text: $app.settings.defaultCategoryId, keyboard: .numberPad, monospaced: true)
                            .onChange(of: app.settings.defaultCategoryId) { _ in app.saveSettings() }
                        Text("Se aplica por defecto a los productos que subas. Puedes revisarla en cada producto antes de publicar.")
                            .font(.system(size: 11.5)).foregroundStyle(Color.black.opacity(0.45))
                    }

                    VStack(alignment: .leading, spacing: 8) {
                        Text("Clave de Discogs (opcional)").font(.system(size: 13, weight: .semibold))
                        LabeledTextField(placeholder: "Personal access token", text: $app.settings.discogsToken, autocapitalization: .never, monospaced: true)
                            .onChange(of: app.settings.discogsToken) { _ in app.saveSettings() }
                        Text("Sin esto, Discogs no muestra las portadas en la lista de resultados de la búsqueda. Genera una gratis en discogs.com → Configuración → Desarrolladores → Generar nuevo token.")
                            .font(.system(size: 11.5)).foregroundStyle(Color.black.opacity(0.45))
                    }

                    VStack(alignment: .leading, spacing: 6) {
                        Text("Datos de Discogs a incluir en el producto").font(.system(size: 13, weight: .semibold))
                        VStack(spacing: 0) {
                            ForEach(FieldDef.all) { field in
                                Button {
                                    app.toggleField(field.key)
                                } label: {
                                    HStack {
                                        Text(field.label).font(.system(size: 13.5, weight: .medium)).foregroundStyle(.primary)
                                        Spacer()
                                        Toggle("", isOn: Binding(
                                            get: { app.settings.fieldToggles[field.key] ?? true },
                                            set: { _ in app.toggleField(field.key) }
                                        ))
                                        .labelsHidden()
                                        .tint(Theme.successGreen)
                                        .allowsHitTesting(false)
                                    }
                                    .padding(.vertical, 12)
                                    .padding(.horizontal, 13)
                                    .background(Color.white)
                                }
                                Divider().opacity(0.5)
                            }
                        }
                        .clipShape(RoundedRectangle(cornerRadius: 10))
                    }
                }
                .padding(.horizontal, 20)
                .padding(.bottom, 24)
            }
        }
    }
}

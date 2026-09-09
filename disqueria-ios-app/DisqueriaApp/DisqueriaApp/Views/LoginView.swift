import SwiftUI

struct LoginView: View {
    @EnvironmentObject var app: AppState

    var body: some View {
        ScrollView {
            VStack(spacing: 20) {
                VStack(spacing: 6) {
                    Text("D")
                        .font(.system(size: 26, weight: .bold))
                        .foregroundStyle(.white)
                        .frame(width: 64, height: 64)
                        .background(Theme.ink)
                        .clipShape(RoundedRectangle(cornerRadius: 16))
                    Text("Disquería App")
                        .font(.system(size: 19, weight: .bold))
                        .padding(.top, 6)
                    Text("Conecta la app a tu propia tienda WordPress/WooCommerce")
                        .font(.system(size: 13))
                        .foregroundStyle(Theme.subtleText)
                        .multilineTextAlignment(.center)
                }
                .padding(.bottom, 8)

                VStack(spacing: 10) {
                    LabeledTextField(placeholder: "URL de tu tienda (ej. tudisqueria.cl)", text: $app.siteUrl, keyboard: .URL, autocapitalization: .never)
                    LabeledTextField(placeholder: "usuario de WordPress", text: $app.username, autocapitalization: .never)
                    SecureField("contraseña de aplicación", text: $app.appPassword)
                        .font(.system(size: 14, design: .monospaced))
                        .padding(12)
                        .background(Color.white)
                        .overlay(RoundedRectangle(cornerRadius: 10).stroke(Color.black.opacity(0.2), lineWidth: 1))
                        .clipShape(RoundedRectangle(cornerRadius: 10))
                }

                if !app.loginError.isEmpty {
                    ErrorBanner(message: app.loginError)
                }

                PrimaryButton(title: "Entrar") { app.loginManual() }

                Text("Genera una contraseña de aplicación en tu WordPress: Usuarios → Perfil → Contraseñas de aplicación.")
                    .font(.system(size: 11))
                    .foregroundStyle(Color.black.opacity(0.4))

                HStack(spacing: 10) {
                    Rectangle().fill(Color.black.opacity(0.15)).frame(height: 1)
                    Text("o automático").font(.system(size: 11, weight: .medium)).foregroundStyle(Color.black.opacity(0.4))
                    Rectangle().fill(Color.black.opacity(0.15)).frame(height: 1)
                }

                SecondaryButton(title: "Conectar con mi tienda") { app.connectWp() }

                Text("Te lleva a iniciar sesión en tu WordPress. Usa esto solo si sabes que no hay otra cuenta guardada — si no, prefiere el método manual arriba.")
                    .font(.system(size: 11))
                    .foregroundStyle(Color.black.opacity(0.4))
            }
            .padding(24)
            .padding(.top, 16)
        }
    }
}

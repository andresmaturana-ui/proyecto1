import SwiftUI

struct NewUsedView: View {
    @EnvironmentObject var app: AppState

    var body: some View {
        VStack(spacing: 0) {
            StepHeader(title: "Estado del disco", step: "2/6", onBack: { app.screen = .match })

            if !app.coverUrl.isEmpty {
                HStack(spacing: 10) {
                    RemoteThumb(url: DiscogsAPI.proxiedImageUrl(app.coverUrl), size: 48, cornerRadius: 6)
                    Text("Portada encontrada en Discogs")
                        .font(.system(size: 11.5, design: .monospaced))
                        .foregroundStyle(Color.black.opacity(0.55))
                    Spacer()
                }
                .padding(10)
                .background(Color.white)
                .clipShape(RoundedRectangle(cornerRadius: 10))
                .padding(.horizontal, 20)
                .padding(.bottom, 14)
            }

            VStack(alignment: .leading, spacing: 10) {
                Text("Elige si el disco es nuevo (sellado/sin uso) o usado. Si es usado, luego podrás fotografiarlo y calificar su estado.")
                    .font(.system(size: 13)).foregroundStyle(Theme.subtleText)
                HStack(spacing: 10) {
                    SecondaryButton(title: "Nuevo") { app.pickNewFlow() }
                    Button(action: { app.pickUsedFlow() }) {
                        Text("Usado")
                            .font(.system(size: 14.5, weight: .semibold))
                            .frame(maxWidth: .infinity)
                            .padding(.vertical, 16)
                            .foregroundStyle(.white)
                            .background(Theme.ink)
                            .clipShape(RoundedRectangle(cornerRadius: 11))
                    }
                }
            }
            .padding(.horizontal, 20)

            Spacer()
        }
    }
}

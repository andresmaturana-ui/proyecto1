import SwiftUI

struct MatchResultsView: View {
    @EnvironmentObject var app: AppState

    var body: some View {
        VStack(spacing: 0) {
            StepHeader(title: "Resultados del código", onBack: { app.screen = .scan })
            ScrollView {
                VStack(spacing: 10) {
                    if app.results.isEmpty {
                        Text("Discogs no encontró ediciones para ese código. Verifica el número o agrega el release manualmente después.")
                            .font(.system(size: 13.5))
                            .foregroundStyle(Color.black.opacity(0.6))
                            .padding(16)
                            .frame(maxWidth: .infinity, alignment: .leading)
                            .background(Color.white)
                            .clipShape(RoundedRectangle(cornerRadius: 10))
                    }
                    ForEach(app.results) { item in
                        Button {
                            Task { await app.pickResult(item) }
                        } label: {
                            HStack(spacing: 12) {
                                RemoteThumb(url: item.thumbUrl, size: 56, cornerRadius: 6)
                                VStack(alignment: .leading, spacing: 3) {
                                    Text(item.displayTitle)
                                        .font(.system(size: 13.5, weight: .semibold))
                                        .foregroundStyle(.primary)
                                        .lineLimit(1)
                                    Text(item.metaLine)
                                        .font(.system(size: 11.5, design: .monospaced))
                                        .foregroundStyle(Color.black.opacity(0.55))
                                        .lineLimit(1)
                                }
                                Spacer()
                            }
                            .padding(11)
                            .background(Color.white)
                            .overlay(RoundedRectangle(cornerRadius: 11).stroke(Color.black.opacity(0.12), lineWidth: 1))
                            .clipShape(RoundedRectangle(cornerRadius: 11))
                        }
                    }
                }
                .padding(.horizontal, 20)
                .padding(.bottom, 16)
            }
        }
    }
}

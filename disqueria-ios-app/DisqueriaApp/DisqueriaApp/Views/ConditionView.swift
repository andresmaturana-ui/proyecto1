import SwiftUI

struct ConditionView: View {
    @EnvironmentObject var app: AppState

    var body: some View {
        VStack(spacing: 0) {
            StepHeader(title: "Condición", step: "4/6", onBack: { app.screen = .photos })
            ScrollView {
                VStack(alignment: .leading, spacing: 20) {
                    Text("Califica el disco y la carátula por separado, usando la escala Goldmine (Mint es el mejor estado, Good el más gastado).")
                        .font(.system(size: 13)).foregroundStyle(Theme.subtleText)

                    gradeSection(title: "Estado del disco (sin la carátula)", selected: $app.media)
                    gradeSection(title: "Estado de la carátula", selected: $app.sleeve)

                    VStack(alignment: .leading, spacing: 7) {
                        Text("Notas").font(.system(size: 13, weight: .semibold))
                        TextEditor(text: $app.notes)
                            .font(.system(size: 13.5))
                            .frame(minHeight: 70)
                            .padding(6)
                            .background(Color.white)
                            .overlay(RoundedRectangle(cornerRadius: 9).stroke(Color.black.opacity(0.2), lineWidth: 1))
                            .clipShape(RoundedRectangle(cornerRadius: 9))
                            .overlay(alignment: .topLeading) {
                                if app.notes.isEmpty {
                                    Text("Roce leve en la contraportada…")
                                        .font(.system(size: 13.5))
                                        .foregroundStyle(Color.black.opacity(0.3))
                                        .padding(.horizontal, 11).padding(.top, 14)
                                        .allowsHitTesting(false)
                                }
                            }
                    }
                }
                .padding(.horizontal, 20)
                .padding(.top, 14)
                .padding(.bottom, 16)
            }

            VStack {
                PrimaryButton(title: "Revisar datos") { app.screen = .details }
            }
            .padding(.horizontal, 20).padding(.vertical, 12)
            .background(Color.white)
            .overlay(Rectangle().frame(height: 1).foregroundStyle(Color.black.opacity(0.12)), alignment: .top)
        }
    }

    @ViewBuilder
    private func gradeSection(title: String, selected: Binding<GoldmineGrade>) -> some View {
        VStack(alignment: .leading, spacing: 8) {
            Text(title).font(.system(size: 13, weight: .semibold))
            FlowLayout(spacing: 7) {
                ForEach(GoldmineGrade.allCases) { grade in
                    ChipButton(label: grade.rawValue, state: selected.wrappedValue == grade ? .selected : .unselected) {
                        selected.wrappedValue = grade
                    }
                }
            }
        }
    }
}

/// Simple wrapping row layout, standing in for the CSS `flex-wrap` grids used for
/// grade/category chips in the web version.
struct FlowLayout: Layout {
    var spacing: CGFloat = 8

    func sizeThatFits(proposal: ProposedViewSize, subviews: Subviews, cache: inout ()) -> CGSize {
        let maxWidth = proposal.width ?? .infinity
        var x: CGFloat = 0, y: CGFloat = 0, rowHeight: CGFloat = 0
        for subview in subviews {
            let size = subview.sizeThatFits(.unspecified)
            if x + size.width > maxWidth, x > 0 {
                x = 0; y += rowHeight + spacing; rowHeight = 0
            }
            x += size.width + spacing
            rowHeight = max(rowHeight, size.height)
        }
        return CGSize(width: maxWidth, height: y + rowHeight)
    }

    func placeSubviews(in bounds: CGRect, proposal: ProposedViewSize, subviews: Subviews, cache: inout ()) {
        let maxWidth = bounds.width
        var x: CGFloat = bounds.minX, y: CGFloat = bounds.minY, rowHeight: CGFloat = 0
        for subview in subviews {
            let size = subview.sizeThatFits(.unspecified)
            if x + size.width > bounds.minX + maxWidth, x > bounds.minX {
                x = bounds.minX; y += rowHeight + spacing; rowHeight = 0
            }
            subview.place(at: CGPoint(x: x, y: y), proposal: ProposedViewSize(size))
            x += size.width + spacing
            rowHeight = max(rowHeight, size.height)
        }
    }
}

import SwiftUI
import UIKit

enum Theme {
    static let background = Color(red: 0.898, green: 0.898, blue: 0.918) // #E5E5EA
    static let card = Color(red: 0.949, green: 0.949, blue: 0.969) // #F2F2F7
    static let ink = Color(red: 0.067, green: 0.067, blue: 0.067) // #111111
    static let errorBg = Color(red: 1.0, green: 0.922, blue: 0.922) // #FFEBEB
    static let errorFg = Color(red: 0.702, green: 0.137, blue: 0.137) // #B32323
    static let successGreen = Color(red: 0.204, green: 0.780, blue: 0.349) // #34C759
    static let subtleText = Color.black.opacity(0.55)
}

/// A full-width black primary action button, used throughout the flow
/// ("Entrar", "Publicar en mi tienda", "Revisar datos", etc).
struct PrimaryButton: View {
    let title: String
    var disabled: Bool = false
    let action: () -> Void

    var body: some View {
        Button(action: action) {
            Text(title)
                .font(.system(size: 15, weight: .semibold))
                .frame(maxWidth: .infinity)
                .padding(.vertical, 14)
                .foregroundStyle(.white)
                .background(disabled ? Theme.ink.opacity(0.4) : Theme.ink)
                .clipShape(RoundedRectangle(cornerRadius: 11))
        }
        .disabled(disabled)
    }
}

/// Outlined secondary button ("Conectar con mi tienda", "Escanear con cámara"…).
struct SecondaryButton: View {
    let title: String
    var action: () -> Void

    var body: some View {
        Button(action: action) {
            Text(title)
                .font(.system(size: 14, weight: .semibold))
                .frame(maxWidth: .infinity)
                .padding(.vertical, 13)
                .foregroundStyle(Theme.ink)
                .background(Color.white)
                .overlay(RoundedRectangle(cornerRadius: 11).stroke(Theme.ink, lineWidth: 1))
                .clipShape(RoundedRectangle(cornerRadius: 11))
        }
    }
}

struct ErrorBanner: View {
    let message: String
    var body: some View {
        Text(message)
            .font(.system(size: 12.5))
            .foregroundStyle(Theme.errorFg)
            .padding(11)
            .frame(maxWidth: .infinity, alignment: .leading)
            .background(Theme.errorBg)
            .clipShape(RoundedRectangle(cornerRadius: 9))
    }
}

/// A selectable pill used for grading (Goldmine scale), format, condition and
/// genre categories. Three visual states: selected, unselected, "missing" (i.e.
/// the category doesn't exist yet in the store and can be created on tap).
struct ChipButton: View {
    enum State { case selected, unselected, missing }
    let label: String
    let state: State
    var busy: Bool = false
    var missingSuffix: String = "crear"
    let action: () -> Void

    var body: some View {
        Button(action: action) {
            Group {
                if state == .missing {
                    Text("\(label) · \(busy ? "creando…" : missingSuffix)")
                } else {
                    Text(label)
                }
            }
            .font(.system(size: 12.5, weight: state == .selected ? .semibold : .medium))
            .padding(.vertical, 9)
            .padding(.horizontal, 12)
            .foregroundStyle(foreground)
            .background(background)
            .overlay(
                RoundedRectangle(cornerRadius: 8)
                    .stroke(border, style: StrokeStyle(lineWidth: state == .selected ? 1.5 : 1, dash: state == .missing ? [4, 3] : []))
            )
            .clipShape(RoundedRectangle(cornerRadius: 8))
        }
        .disabled(busy)
    }

    private var background: Color {
        switch state {
        case .selected: return Theme.ink
        case .unselected, .missing: return .white
        }
    }
    private var foreground: Color {
        switch state {
        case .selected: return .white
        case .unselected: return Color.black.opacity(0.65)
        case .missing: return Color.black.opacity(0.6)
        }
    }
    private var border: Color {
        switch state {
        case .selected: return Theme.ink
        case .unselected: return Color.black.opacity(0.2)
        case .missing: return Color.black.opacity(0.4)
        }
    }
}

/// Screen header with a back chevron and a title, used on every step past login.
struct StepHeader: View {
    let title: String
    var step: String? = nil
    var onBack: (() -> Void)? = nil

    var body: some View {
        HStack(spacing: 10) {
            if let onBack {
                Button(action: onBack) {
                    Image(systemName: "chevron.left")
                        .font(.system(size: 18, weight: .medium))
                        .foregroundStyle(Theme.ink)
                }
            }
            Text(title)
                .font(.system(size: 18, weight: .bold))
                .frame(maxWidth: .infinity, alignment: .leading)
            if let step {
                Text(step)
                    .font(.system(size: 11, weight: .medium, design: .monospaced))
                    .foregroundStyle(Color.black.opacity(0.5))
            }
        }
        .padding(.horizontal, 20)
        .padding(.top, 16)
        .padding(.bottom, 10)
    }
}

struct LabeledTextField: View {
    let placeholder: String
    @Binding var text: String
    var keyboard: UIKeyboardType = .default
    var monospaced: Bool = false
    var autocapitalization: TextInputAutocapitalization = .sentences

    var body: some View {
        TextField(placeholder, text: $text)
            .font(monospaced ? .system(size: 14, design: .monospaced) : .system(size: 15))
            .keyboardType(keyboard)
            .textInputAutocapitalization(autocapitalization)
            .autocorrectionDisabled(autocapitalization == .never)
            .padding(12)
            .background(Color.white)
            .overlay(RoundedRectangle(cornerRadius: 10).stroke(Color.black.opacity(0.2), lineWidth: 1))
            .clipShape(RoundedRectangle(cornerRadius: 10))
    }
}

/// Remote image with a neutral placeholder, standing in for the CSS
/// background-image thumbnails in the web version.
struct RemoteThumb: View {
    let url: String?
    var size: CGFloat = 56
    var cornerRadius: CGFloat = 6

    var body: some View {
        AsyncImage(url: url.flatMap(URL.init(string:))) { phase in
            if let image = phase.image {
                image.resizable().aspectRatio(contentMode: .fill)
            } else {
                Color(red: 0.863, green: 0.863, blue: 0.882) // #DCDCE1
            }
        }
        .frame(width: size, height: size)
        .clipShape(RoundedRectangle(cornerRadius: cornerRadius))
    }
}

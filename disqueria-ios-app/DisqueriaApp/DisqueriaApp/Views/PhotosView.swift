import SwiftUI
import UIKit

struct PhotosView: View {
    @EnvironmentObject var app: AppState
    @State private var showingCamera = false
    @State private var cameraUnavailable = false

    private var label: String {
        if app.photo1 != nil && app.photo2 != nil { return "Fotos listas" }
        if app.photo1 == nil { return "Fotos (opcional)" }
        return "Foto 2 · el medio"
    }

    var body: some View {
        VStack(spacing: 0) {
            StepHeader(title: label, step: "3/6", onBack: { app.screen = .newUsed })
            Text("Fotografía la carátula y el disco para mostrar su estado real. Es opcional, puedes omitirlo.")
                .font(.system(size: 13)).foregroundStyle(Theme.subtleText)
                .padding(.horizontal, 20).padding(.bottom, 4)

            ScrollView {
                VStack(spacing: 14) {
                    HStack(spacing: 10) {
                        photoSlot(label: "carátula", image: app.photo1) { app.removePhoto(slot: 1) }
                        photoSlot(label: "medio", image: app.photo2) { app.removePhoto(slot: 2) }
                    }

                    if cameraUnavailable {
                        Text("No se pudo acceder a la cámara. Revisa los permisos del navegador.")
                            .font(.system(size: 12)).foregroundStyle(Theme.errorFg)
                            .padding(10).frame(maxWidth: .infinity, alignment: .leading)
                            .background(Theme.errorBg).clipShape(RoundedRectangle(cornerRadius: 9))
                    } else {
                        SecondaryButton(title: "Abrir cámara") {
                            if UIImagePickerController.isSourceTypeAvailable(.camera) {
                                showingCamera = true
                            } else {
                                cameraUnavailable = true
                            }
                        }
                        .disabled(app.photo1 != nil && app.photo2 != nil)
                    }

                    HStack(spacing: 10) {
                        if app.photo1 != nil || app.photo2 != nil {
                            PrimaryButton(title: "Continuar") { app.screen = .condition }
                        } else {
                            SecondaryButton(title: "Omitir fotos") { app.screen = .condition }
                        }
                    }
                }
                .padding(.horizontal, 20)
                .padding(.bottom, 16)
            }
        }
        .fullScreenCover(isPresented: $showingCamera) {
            CameraCaptureView(
                onCaptured: { image in app.photoTaken(image); showingCamera = false },
                onCancel: { showingCamera = false }
            )
            .ignoresSafeArea()
        }
    }

    @ViewBuilder
    private func photoSlot(label: String, image: UIImage?, remove: @escaping () -> Void) -> some View {
        if let image {
            ZStack(alignment: .topTrailing) {
                Image(uiImage: image)
                    .resizable()
                    .aspectRatio(contentMode: .fill)
                    .frame(height: 100)
                    .frame(maxWidth: .infinity)
                    .clipShape(RoundedRectangle(cornerRadius: 10))
                    .overlay(RoundedRectangle(cornerRadius: 10).stroke(Theme.ink, lineWidth: 1.5))
                Text(label)
                    .font(.system(size: 10, weight: .medium, design: .monospaced))
                    .foregroundStyle(.white)
                    .padding(.horizontal, 6).padding(.vertical, 3)
                    .background(Color.black.opacity(0.55))
                    .clipShape(RoundedRectangle(cornerRadius: 4))
                    .padding(5)
                Button(action: remove) {
                    Image(systemName: "xmark")
                        .font(.system(size: 11, weight: .semibold))
                        .foregroundStyle(.white)
                        .frame(width: 22, height: 22)
                        .background(Color.black.opacity(0.55))
                        .clipShape(Circle())
                }
                .padding(5)
                .frame(maxWidth: .infinity, alignment: .trailing)
            }
        } else {
            VStack {
                Text(label)
                    .font(.system(size: 11, design: .monospaced))
                    .foregroundStyle(Color.black.opacity(0.4))
            }
            .frame(height: 100)
            .frame(maxWidth: .infinity)
            .overlay(RoundedRectangle(cornerRadius: 10).strokeBorder(Color.black.opacity(0.25), style: StrokeStyle(lineWidth: 1.5, dash: [4, 3])))
        }
    }
}

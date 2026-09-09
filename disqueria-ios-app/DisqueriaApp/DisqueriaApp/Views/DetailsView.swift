import SwiftUI

struct DetailsView: View {
    @EnvironmentObject var app: AppState
    @State private var pendingCreation: PendingCreation?

    struct PendingCreation: Identifiable {
        let id = UUID()
        let name: String
        let kind: Kind
        enum Kind { case genre, format, condition }
    }

    private struct Field: Identifiable {
        let id = UUID()
        let key: String
        let value: String
    }

    private struct CategoryChip: Identifiable {
        let id: String
        let label: String
        let state: ChipButton.State
        let isCreate: Bool
        let createName: String?
    }

    private var fields: [Field] {
        guard let r = app.selectedResult else { return [] }
        let full = app.fullRelease
        let tg = app.settings.fieldToggles
        var out: [Field] = []
        if tg["fTitle"] == true { out.append(Field(key: "título", value: r.title ?? "—")) }
        if tg["fArtist"] == true { out.append(Field(key: "artista", value: full?.artistNames.isEmpty == false ? full!.artistNames : "—")) }
        if tg["fYear"] == true { out.append(Field(key: "año", value: full?.year.map { String($0) } ?? "—")) }
        if tg["fLabel"] == true { out.append(Field(key: "sello", value: full?.labelNames.isEmpty == false ? full!.labelNames : "—")) }
        if tg["fCountry"] == true { out.append(Field(key: "país", value: full?.country ?? "—")) }
        if tg["fGenre"] == true { out.append(Field(key: "género", value: full?.genreStyleString.isEmpty == false ? full!.genreStyleString : "—")) }
        if tg["fFormat"] == true { out.append(Field(key: "formato", value: full?.formatString.isEmpty == false ? full!.formatString : "—")) }
        if tg["fTracklist"] == true {
            let names = full?.trackNames.replacingOccurrences(of: "\n", with: " / ") ?? ""
            out.append(Field(key: "tracklist", value: names.isEmpty ? "—" : names))
        }
        if tg["fNotes"] == true {
            let n = full?.notes ?? ""
            let short = n.count > 60 ? String(n.prefix(60)) + "…" : n
            out.append(Field(key: "notas", value: n.isEmpty ? "—" : short))
        }
        return out
    }

    private var formatChips: [CategoryChip] {
        var chips: [CategoryChip] = app.categories
            .filter { $0.name.range(of: "vinilo|vinyl|\\blp\\b|\\bcd\\b|cassette|cinta", options: [.regularExpression, .caseInsensitive]) != nil }
            .map { CategoryChip(id: String($0.id), label: $0.name, state: app.selectedFormatCat == $0.id ? .selected : .unselected, isCreate: false, createName: nil) }
        if let g = app.formatGuess, g.category == nil {
            chips.append(CategoryChip(id: "new-\(g.name)", label: g.name, state: .missing, isCreate: true, createName: g.name))
        }
        return chips
    }

    private var conditionChips: [CategoryChip] {
        var chips: [CategoryChip] = app.categories
            .filter { $0.name.range(of: "nuevo|usado|new|used", options: [.regularExpression, .caseInsensitive]) != nil }
            .map { CategoryChip(id: String($0.id), label: $0.name, state: app.selectedConditionCat == $0.id ? .selected : .unselected, isCreate: false, createName: nil) }
        if let g = app.conditionGuess, g.category == nil {
            chips.append(CategoryChip(id: "new-\(g.name)", label: g.name, state: .missing, isCreate: true, createName: g.name))
        }
        return chips
    }

    var body: some View {
        VStack(spacing: 0) {
            StepHeader(title: "Datos", step: "5/6", onBack: { app.goBackFromDetails() })
            ScrollView {
                VStack(alignment: .leading, spacing: 16) {
                    Text("Esto es lo que trajimos de Discogs. Revisa la etiqueta y elige o crea las categorías de tu tienda antes de continuar.")
                        .font(.system(size: 13)).foregroundStyle(Theme.subtleText)

                    VStack(spacing: 0) {
                        ForEach(fields) { f in
                            HStack(alignment: .top) {
                                Text(f.key).font(.system(size: 11.5, design: .monospaced)).foregroundStyle(Color.black.opacity(0.45)).frame(width: 80, alignment: .leading)
                                Spacer()
                                Text(f.value).font(.system(size: 13, weight: .medium)).multilineTextAlignment(.trailing)
                            }
                            .padding(.horizontal, 13).padding(.vertical, 11)
                            Divider().opacity(0.5)
                        }
                        Text("Estos campos se toman de Ajustes. Cámbialos ahí si falta algo.")
                            .font(.system(size: 11)).foregroundStyle(Color.black.opacity(0.4))
                            .padding(.horizontal, 13).padding(.vertical, 10)
                    }
                    .background(Color.white)
                    .clipShape(RoundedRectangle(cornerRadius: 10))

                    VStack(alignment: .leading, spacing: 7) {
                        Text("Etiqueta (género/estilo)").font(.system(size: 13, weight: .semibold))
                        LabeledTextField(placeholder: "ej. Rock progresivo", text: $app.tagInput)
                        Text("Se usará como etiqueta del producto. Si no existe en la tienda, se crea sola.")
                            .font(.system(size: 11.5)).foregroundStyle(Color.black.opacity(0.45))
                    }

                    if app.categories.isEmpty {
                        Text("No se pudieron cargar las categorías de tu tienda.\(app.categoriesError.isEmpty ? "" : " (\(app.categoriesError))")")
                            .font(.system(size: 12)).foregroundStyle(Color.black.opacity(0.5))
                            .padding(10).background(Color.white).clipShape(RoundedRectangle(cornerRadius: 9))
                    }

                    VStack(alignment: .leading, spacing: 7) {
                        Text("Formato").font(.system(size: 13, weight: .semibold))
                        FlowLayout(spacing: 7) {
                            ForEach(formatChips, id: \.id) { chip in
                                ChipButton(label: chip.label, state: chip.state, busy: app.creatingCatName == (chip.createName ?? "")) {
                                    if chip.isCreate, let name = chip.createName {
                                        pendingCreation = PendingCreation(name: name, kind: .format)
                                    } else if let id = Int(chip.id) {
                                        app.selectedFormatCat = id
                                    }
                                }
                            }
                        }
                    }

                    VStack(alignment: .leading, spacing: 7) {
                        Text("Condición").font(.system(size: 13, weight: .semibold))
                        if !app.categories.contains(where: { $0.name.range(of: "nuevo|usado|new|used", options: [.regularExpression, .caseInsensitive]) != nil }) && app.conditionGuess == nil {
                            Text("Crea categorías \"Nuevo\" y \"Usado\" en tu tienda para poder elegirlas aquí.")
                                .font(.system(size: 11.5)).foregroundStyle(Color.black.opacity(0.45))
                        }
                        FlowLayout(spacing: 7) {
                            ForEach(conditionChips, id: \.id) { chip in
                                ChipButton(label: chip.label, state: chip.state, busy: app.creatingCatName == (chip.createName ?? "")) {
                                    if chip.isCreate, let name = chip.createName {
                                        pendingCreation = PendingCreation(name: name, kind: .condition)
                                    } else if let id = Int(chip.id) {
                                        app.selectedConditionCat = id
                                    }
                                }
                            }
                        }
                    }

                    VStack(alignment: .leading, spacing: 7) {
                        Text("Estilo musical (de Discogs)").font(.system(size: 13, weight: .semibold))
                        FlowLayout(spacing: 7) {
                            ForEach(app.genreGuesses, id: \.name) { guess in
                                if let cat = guess.category {
                                    ChipButton(label: cat.name, state: app.selectedGenreCats.contains(cat.id) ? .selected : .unselected) {
                                        if app.selectedGenreCats.contains(cat.id) { app.selectedGenreCats.remove(cat.id) }
                                        else { app.selectedGenreCats.insert(cat.id) }
                                    }
                                } else {
                                    ChipButton(label: guess.name, state: .missing, busy: app.creatingCatName == guess.name) {
                                        pendingCreation = PendingCreation(name: guess.name, kind: .genre)
                                    }
                                }
                            }
                        }
                    }
                }
                .padding(.horizontal, 20)
                .padding(.top, 6)
                .padding(.bottom, 16)
            }

            VStack {
                PrimaryButton(title: "Poner precio") { app.screen = .price }
            }
            .padding(.horizontal, 20).padding(.vertical, 12)
            .background(Color.white)
            .overlay(Rectangle().frame(height: 1).foregroundStyle(Color.black.opacity(0.12)), alignment: .top)
        }
        .alert(item: $pendingCreation) { pending in
            Alert(
                title: Text("Crear categoría \"\(pending.name)\" en tu tienda?"),
                primaryButton: .default(Text("Crear")) {
                    Task {
                        switch pending.kind {
                        case .genre: await app.createGenreCategory(name: pending.name)
                        case .format: await app.createNamedCategory(name: pending.name, kind: "format")
                        case .condition: await app.createNamedCategory(name: pending.name, kind: "condition")
                        }
                    }
                },
                secondaryButton: .cancel(Text("Cancelar"))
            )
        }
        .alert("Error", isPresented: Binding(get: { app.alertMessage != nil }, set: { if !$0 { app.alertMessage = nil } })) {
            Button("OK") { app.alertMessage = nil }
        } message: {
            Text(app.alertMessage ?? "")
        }
    }
}

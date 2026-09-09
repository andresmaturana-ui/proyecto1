import Foundation
import SwiftUI
import UIKit

enum Screen {
    case login, settings, scan, match, newUsed, photos, condition, details, price, done, list, editProduct
}

/// Central app state and business logic, mirroring the `Component` class in the
/// original plugin's app/index.html — one screen enum drives navigation, and every
/// action here corresponds 1:1 to a handler over there (lookup, pickResult, publish,
/// loadList, saveEdit, deleteProduct, etc).
@MainActor
final class AppState: ObservableObject {
    // Auth / shell
    @Published var screen: Screen = .login
    @Published var siteUrl: String = ""
    @Published var username: String = ""
    @Published var appPassword: String = ""
    @Published var loginError: String = ""
    @Published var isLoggedIn: Bool = false
    @Published var siteName: String = ""
    @Published var siteLogo: String = ""
    @Published var alertMessage: String?

    // Settings
    @Published var settings = StoreSettings()

    // Scan
    @Published var barcodeInput: String = ""
    @Published var lookupBusy = false
    @Published var lookupError = ""
    @Published var results: [DiscogsSearchItem] = []
    @Published var barcodeCameraActive = false

    // Selected release
    @Published var selectedResult: DiscogsSearchItem?
    @Published var fullRelease: DiscogsRelease?
    @Published var coverUrl: String = ""

    // New/used
    @Published var conditionType: String = "usado"

    // Photos
    @Published var photo1: UIImage?
    @Published var photo2: UIImage?

    // Condition
    @Published var media: GoldmineGrade = .vgPlus
    @Published var sleeve: GoldmineGrade = .vgPlus
    @Published var notes: String = ""

    // Details
    @Published var tagInput: String = ""
    @Published var categories: [WooCategory] = []
    @Published var categoriesError: String = ""
    @Published var selectedFormatCat: Int?
    @Published var selectedConditionCat: Int?
    @Published var selectedGenreCats: Set<Int> = []
    @Published var genreGuesses: [CategoryGuess] = []
    @Published var formatGuess: CategoryGuess?
    @Published var conditionGuess: CategoryGuess?
    @Published var creatingCatName: String = ""

    // Price / publish
    @Published var price: String = ""
    @Published var stock: String = ""
    @Published var publishBusy = false
    @Published var publishError: String = ""
    @Published var doneMsg: String = ""

    // List
    @Published var products: [WooProductSummary] = []
    @Published var listError: String = ""

    // Edit
    @Published var editId: Int?
    @Published var editTitle: String = ""
    @Published var editPrice: String = ""
    @Published var editStock: String = ""
    @Published var editBusy = false
    @Published var editError: String = ""

    private var api: WooCommerceAPI { WooCommerceAPI(siteUrl: siteUrl, username: username, appPassword: appPassword) }

    // MARK: - Bootstrap / auth

    func bootstrap() {
        guard let creds = KeychainStore.load(), !creds.appPassword.isEmpty else { return }
        siteUrl = creds.siteUrl
        username = creds.username
        appPassword = creds.appPassword
        finishLogin(site: creds.siteUrl)
    }

    /// Called from the app's .onOpenURL when WordPress redirects back to
    /// disqueriaapp://authorize?site_url=...&user_login=...&password=... after the
    /// user approves the app on the "Conectar con mi tienda" flow.
    func handleAuthorizationCallback(_ url: URL) {
        guard let comps = URLComponents(url: url, resolvingAgainstBaseURL: false) else { return }
        var params: [String: String] = [:]
        for item in comps.queryItems ?? [] { params[item.name] = item.value }
        guard let userLogin = params["user_login"], let password = params["password"], !userLogin.isEmpty, !password.isEmpty else { return }
        let site = WooCommerceAPI.normalize(params["site_url"] ?? siteUrl)
        siteUrl = site
        username = userLogin
        appPassword = password
        KeychainStore.save(.init(siteUrl: site, username: userLogin, appPassword: password))
        finishLogin(site: site)
    }

    func connectWp() {
        let trimmed = siteUrl.trimmingCharacters(in: .whitespacesAndNewlines)
        guard !trimmed.isEmpty else { loginError = "Ingresa la URL de tu tienda."; return }
        let site = WooCommerceAPI.normalize(trimmed)
        siteUrl = site
        var comps = URLComponents(string: site + "/wp-admin/authorize-application.php")
        comps?.queryItems = [
            URLQueryItem(name: "app_name", value: "Disquería App"),
            URLQueryItem(name: "success_url", value: "disqueriaapp://authorize"),
        ]
        if let url = comps?.url {
            UIApplication.shared.open(url)
        }
    }

    func loginManual() {
        let site = siteUrl.trimmingCharacters(in: .whitespacesAndNewlines)
        let user = username.trimmingCharacters(in: .whitespacesAndNewlines)
        let pass = appPassword.trimmingCharacters(in: .whitespacesAndNewlines)
        guard !site.isEmpty, !user.isEmpty, !pass.isEmpty else {
            loginError = "Completa tienda, usuario y contraseña de aplicación."
            return
        }
        let normalized = WooCommerceAPI.normalize(site)
        siteUrl = normalized
        username = user
        appPassword = pass
        KeychainStore.save(.init(siteUrl: normalized, username: user, appPassword: pass))
        loginError = ""
        finishLogin(site: normalized)
    }

    private func finishLogin(site: String) {
        isLoggedIn = true
        screen = .scan
        settings = SettingsStore.load(site: site)
        Task { await loadCategories() }
        Task { await loadSiteInfo() }
    }

    func logout() {
        KeychainStore.clear()
        isLoggedIn = false
        screen = .login
        username = ""
        appPassword = ""
    }

    func saveSettings() {
        SettingsStore.save(settings, site: siteUrl)
    }

    func toggleField(_ key: String) {
        settings.fieldToggles[key] = !(settings.fieldToggles[key] ?? true)
        saveSettings()
    }

    private func loadSiteInfo() async {
        do {
            let info = try await api.siteInfo()
            siteName = info.name ?? ""
            siteLogo = info.logo ?? ""
        } catch {
            // Non-critical: keep the generic "Disquería App" label.
        }
    }

    func loadCategories() async {
        do {
            categories = try await api.categories()
            categoriesError = ""
        } catch {
            categoriesError = error.localizedDescription
        }
    }

    func findCategory(_ keywords: [String]) -> WooCategory? {
        for kw in keywords {
            if let hit = categories.first(where: { $0.name.lowercased().contains(kw) }) { return hit }
        }
        return nil
    }

    // MARK: - Category creation (used from the Details screen chips)

    func createGenreCategory(name: String) async {
        creatingCatName = name
        do {
            let cat = try await api.createCategory(name: name)
            categories.append(cat)
            selectedGenreCats.insert(cat.id)
            if let idx = genreGuesses.firstIndex(where: { $0.name == name }) {
                genreGuesses[idx].category = cat
            }
            creatingCatName = ""
        } catch {
            creatingCatName = ""
            alertMessage = "No se pudo crear la categoría: \(error.localizedDescription)"
        }
    }

    func createNamedCategory(name: String, kind: String) async {
        creatingCatName = name
        do {
            let cat = try await api.createCategory(name: name)
            categories.append(cat)
            if kind == "format" { selectedFormatCat = cat.id; formatGuess = CategoryGuess(name: name, category: cat) }
            if kind == "condition" { selectedConditionCat = cat.id; conditionGuess = CategoryGuess(name: name, category: cat) }
            creatingCatName = ""
        } catch {
            creatingCatName = ""
            alertMessage = "No se pudo crear la categoría: \(error.localizedDescription)"
        }
    }

    // MARK: - Scan / lookup

    func onBarcodeScanned(_ code: String) {
        barcodeCameraActive = false
        barcodeInput = code
        Task { await lookup() }
    }

    func lookup() async {
        let code = barcodeInput.trimmingCharacters(in: .whitespacesAndNewlines)
        guard !code.isEmpty else { lookupError = "Ingresa un código."; return }
        lookupBusy = true
        lookupError = ""
        results = []
        do {
            let token = settings.discogsToken.isEmpty ? nil : settings.discogsToken
            results = try await DiscogsAPI.searchByBarcode(code, token: token)
            lookupBusy = false
            screen = .match
        } catch {
            lookupBusy = false
            lookupError = "Error consultando Discogs: \(error.localizedDescription)"
        }
    }

    func pickResult(_ item: DiscogsSearchItem) async {
        selectedResult = item
        screen = .newUsed
        do {
            let full = try await DiscogsAPI.release(id: item.id)
            fullRelease = full
            coverUrl = full.coverImageUrl ?? item.thumbUrl ?? ""
            tagInput = full.styles?.first ?? full.genres?.first ?? ""

            let fmtName = full.formats?.first?.name ?? ""
            let fmtLower = fmtName.lowercased()
            let fmtKeywordMap: [String: [String]] = ["vinyl": ["vinilo", "vinyl", "lp"], "cd": ["cd"], "cassette": ["cassette", "cinta"]]
            let fmtLabelMap: [String: String] = ["vinyl": "Vinilo", "cd": "CD", "cassette": "Cassette"]
            var fmtKind = ""
            if fmtLower.contains("vinyl") { fmtKind = "vinyl" }
            else if fmtLower.contains("cd") { fmtKind = "cd" }
            else if fmtLower.contains("cassette") { fmtKind = "cassette" }

            if !fmtKind.isEmpty {
                let cat = findCategory(fmtKeywordMap[fmtKind] ?? [])
                formatGuess = CategoryGuess(name: fmtLabelMap[fmtKind] ?? fmtKind, category: cat)
                selectedFormatCat = cat?.id
            } else {
                formatGuess = nil
                selectedFormatCat = nil
            }

            var genres: [String] = []
            for g in (full.genres ?? []) + (full.styles ?? []) where !genres.contains(g) { genres.append(g) }
            genres = Array(genres.prefix(3))
            genreGuesses = genres.map { CategoryGuess(name: $0, category: findCategory([$0.lowercased()])) }
            selectedGenreCats = Set(genreGuesses.compactMap { $0.category?.id })
        } catch {
            // Matches the original app: if Discogs' release detail lookup fails we
            // simply keep the basic search-result info already shown.
        }
    }

    // MARK: - Photos

    func photoTaken(_ image: UIImage) {
        if photo1 == nil { photo1 = image } else { photo2 = image }
    }

    func removePhoto(slot: Int) {
        if slot == 1 { photo1 = nil } else { photo2 = nil }
    }

    // MARK: - New/used, navigation helpers

    func pickNewFlow() {
        let cat = findCategory(["nuevo", "new"])
        conditionType = "nuevo"
        media = .m
        sleeve = .m
        selectedConditionCat = cat?.id
        conditionGuess = CategoryGuess(name: "Nuevo", category: cat)
        screen = .details
    }

    func pickUsedFlow() {
        let cat = findCategory(["usado", "used"])
        conditionType = "usado"
        selectedConditionCat = cat?.id
        conditionGuess = CategoryGuess(name: "Usado", category: cat)
        screen = .photos
    }

    func goBackFromDetails() {
        screen = conditionType == "nuevo" ? .newUsed : .condition
    }

    func resetScan() {
        screen = .scan
        selectedResult = nil
        fullRelease = nil
        coverUrl = ""
        photo1 = nil
        photo2 = nil
        selectedFormatCat = nil
        selectedConditionCat = nil
        selectedGenreCats = []
        genreGuesses = []
        formatGuess = nil
        conditionGuess = nil
        media = .vgPlus
        sleeve = .vgPlus
        notes = ""
        conditionType = "usado"
        tagInput = ""
        price = ""
        stock = ""
        barcodeInput = ""
        results = []
    }

    // MARK: - Publish

    func publish() async {
        publishBusy = true
        publishError = ""
        do {
            var galleryUrls: [String] = []
            if let p1 = photo1 { galleryUrls.append(try await api.uploadMedia(image: p1, filename: "caratula.jpg")) }
            if let p2 = photo2 { galleryUrls.append(try await api.uploadMedia(image: p2, filename: "medio.jpg")) }

            var discogsMediaUrl = ""
            if settings.fieldToggles["fCover"] == true, !coverUrl.isEmpty {
                let proxied = DiscogsAPI.proxiedImageUrl(coverUrl)
                do {
                    discogsMediaUrl = try await api.uploadMedia(remoteUrl: proxied, filename: "discogs-cover.jpg")
                } catch {
                    discogsMediaUrl = (try? await api.uploadMedia(remoteUrl: coverUrl, filename: "discogs-cover.jpg")) ?? ""
                }
            }

            let title = selectedResult?.displayTitle ?? "Producto"
            var cats: [String] = [selectedFormatCat, selectedConditionCat].compactMap { $0 }.map { String($0) }
            cats.append(contentsOf: selectedGenreCats.map { String($0) })

            let full = fullRelease
            let tg = settings.fieldToggles
            var bits: [String] = []
            if tg["fArtist"] == true, let a = full?.artistNames, !a.isEmpty { bits.append(a) }
            if tg["fYear"] == true, let y = full?.year { bits.append(String(y)) }
            if tg["fLabel"] == true, let l = full?.labelNames, !l.isEmpty { bits.append(l) }
            if tg["fCountry"] == true, let c = full?.country, !c.isEmpty { bits.append(c) }
            if tg["fGenre"] == true, let g = full?.genreStyleString, !g.isEmpty { bits.append(g) }
            if tg["fFormat"] == true, let f = full?.formatString, !f.isEmpty { bits.append(f) }
            let discogsLine = bits.joined(separator: " · ")
            let conditionLine = conditionType == "nuevo"
                ? "Estado: Nuevo"
                : "Estado del disco: \(media.rawValue) · Estado de la carátula: \(sleeve.rawValue)"
            let trackNames = full?.trackNames ?? ""
            let tracklistLine = (tg["fTracklist"] == true && !trackNames.isEmpty) ? "Tracklist:\n" + trackNames : ""
            let notesText = full?.notes ?? ""
            let notesLine = (tg["fNotes"] == true && !notesText.isEmpty) ? "Notas: " + notesText : ""
            let shortDesc = [discogsLine, conditionLine, tracklistLine, notesLine, notes].filter { !$0.isEmpty }.joined(separator: "\n\n")

            var body: [String: Any] = [
                "name": title,
                "type": "simple",
                "regular_price": price.isEmpty ? "0" : price,
                "manage_stock": true,
                "stock_quantity": Int(stock) ?? 0,
                "description": notes,
                "short_description": shortDesc,
                "categories": cats,
                "meta_data": [
                    ["key": "condicion_medio", "value": media.rawValue],
                    ["key": "condicion_caratula", "value": sleeve.rawValue],
                    ["key": "discogs_release_id", "value": String(selectedResult?.id ?? 0)],
                ],
            ]

            var tagWarning = ""
            let tagToUse = tagInput.trimmingCharacters(in: .whitespacesAndNewlines)
            if !tagToUse.isEmpty {
                do {
                    let tagId = try await api.resolveTag(name: tagToUse)
                    body["tags"] = [["id": tagId]]
                } catch {
                    tagWarning = "Aviso: no se pudo crear/asignar la etiqueta (\(error.localizedDescription))"
                }
            }

            if !discogsMediaUrl.isEmpty {
                body["featured_image"] = ["src": discogsMediaUrl]
            } else if let first = galleryUrls.first {
                body["featured_image"] = ["src": first]
            }
            if !galleryUrls.isEmpty {
                var gallery = galleryUrls.map { ["src": $0] }
                if !discogsMediaUrl.isEmpty { gallery.append(["src": discogsMediaUrl]) }
                body["gallery_images"] = gallery
            }

            let created = try await api.createProduct(body: body)
            publishBusy = false
            var msg = "Creado: \(created.name ?? "") (id \(created.id))"
            if let permalink = created.permalink, !permalink.isEmpty { msg += "\n" + permalink }
            if !tagWarning.isEmpty { msg += "\n\n" + tagWarning }
            doneMsg = msg
            screen = .done
        } catch {
            publishBusy = false
            publishError = "Error al publicar: \(error.localizedDescription)"
        }
    }

    // MARK: - Product list / edit / delete

    func goList() {
        screen = .list
        Task { await loadList() }
    }

    func loadList() async {
        listError = ""
        products = []
        do {
            products = try await api.products()
        } catch {
            listError = "No se pudo cargar: \(error.localizedDescription) (usuario: \(username))"
        }
    }

    func startEdit(_ p: WooProductSummary) {
        editId = p.id
        editTitle = p.name ?? ""
        editPrice = p.regularPrice ?? ""
        editStock = p.stockQuantity.map { String($0) } ?? ""
        editBusy = false
        editError = ""
        screen = .editProduct
    }

    func cancelEdit() {
        screen = .list
    }

    func saveEdit() async {
        guard let id = editId else { return }
        editBusy = true
        editError = ""
        do {
            var body: [String: Any] = ["name": editTitle, "regular_price": editPrice]
            if let stockVal = Int(editStock) { body["stock_quantity"] = stockVal }
            try await api.updateProduct(id: id, body: body)
            editBusy = false
            screen = .list
            await loadList()
        } catch {
            editBusy = false
            editError = "No se pudo guardar: \(error.localizedDescription)"
        }
    }

    func deleteProduct(_ p: WooProductSummary) async {
        listError = ""
        do {
            try await api.deleteProduct(id: p.id)
            products.removeAll { $0.id == p.id }
        } catch {
            listError = "No se pudo eliminar: \(error.localizedDescription)"
        }
    }

    // MARK: - Display helpers

    var storeLabel: String {
        guard isLoggedIn else { return "Disquería App" }
        if !siteName.isEmpty { return siteName + " App" }
        return WooCommerceAPI.normalize(siteUrl).replacingOccurrences(of: #"^https?://"#, with: "", options: .regularExpression)
    }

    var storeInitial: String {
        siteName.isEmpty ? "D" : String(siteName.prefix(1)).uppercased()
    }
}

import Foundation

/// Per-site app settings (default category, Discogs token, which Discogs fields to
/// copy into the product description). Mirrors `disqueria_app_settings_<site>` in
/// the original web app's localStorage.
struct StoreSettings: Codable {
    var defaultCategoryId: String = ""
    var discogsToken: String = ""
    var fieldToggles: [String: Bool] = Dictionary(uniqueKeysWithValues: FieldDef.all.map { ($0.key, true) })
}

enum SettingsStore {
    private static func key(for site: String) -> String {
        "disqueria_app_settings_" + WooCommerceAPI.normalize(site)
    }

    static func load(site: String) -> StoreSettings {
        guard let data = UserDefaults.standard.data(forKey: key(for: site)),
              let decoded = try? JSONDecoder().decode(StoreSettings.self, from: data) else {
            return StoreSettings()
        }
        var merged = decoded
        for def in FieldDef.all where merged.fieldToggles[def.key] == nil {
            merged.fieldToggles[def.key] = true
        }
        return merged
    }

    static func save(_ settings: StoreSettings, site: String) {
        guard let data = try? JSONEncoder().encode(settings) else { return }
        UserDefaults.standard.set(data, forKey: key(for: site))
    }
}

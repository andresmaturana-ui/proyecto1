import Foundation

/// Discogs is a community-run vinyl/CD database. We use its public search API to
/// resolve a scanned barcode to release candidates and their metadata.
///
/// These are the same "consumer" credentials the original WordPress plugin shipped
/// with as a fallback so the app works out of the box; a user can override them by
/// entering their own personal Discogs token in Settings, same as in the plugin.
enum DiscogsAPI {
    static let consumerKey = "sbBMwBKZwDMQUYnLIQbz"
    static let consumerSecret = "vJqoyJcKNydxgHpIWGRukFbwzEcLgXIi"

    private static func authHeader(token: String?) -> String {
        if let token, !token.isEmpty {
            return "Discogs token=\(token)"
        }
        return "Discogs key=\(consumerKey), secret=\(consumerSecret)"
    }

    static func searchByBarcode(_ barcode: String, token: String?) async throws -> [DiscogsSearchItem] {
        var comps = URLComponents(string: "https://api.discogs.com/database/search")!
        comps.queryItems = [
            URLQueryItem(name: "barcode", value: barcode),
            URLQueryItem(name: "type", value: "release"),
        ]
        guard let url = comps.url else { throw APIError(message: "Código de barras inválido") }
        let headers = [
            "User-Agent": "DisqueriaApp/1.0",
            "Authorization": authHeader(token: token),
        ]
        let res = try await APIClient.request(url: url, headers: headers, as: DiscogsSearchResponse.self)
        return res.results ?? []
    }

    static func release(id: Int) async throws -> DiscogsRelease {
        guard let url = URL(string: "https://api.discogs.com/releases/\(id)") else {
            throw APIError(message: "ID de release inválido")
        }
        let headers = ["User-Agent": "DisqueriaApp/1.0"]
        return try await APIClient.request(url: url, headers: headers, as: DiscogsRelease.self)
    }

    /// Discogs images are hotlink-protected; the plugin proxies them through
    /// images.weserv.nl before handing them to WordPress, and we do the same here.
    static func proxiedImageUrl(_ original: String) -> String {
        let stripped = original.replacingOccurrences(of: #"^https?://"#, with: "", options: .regularExpression)
        let encoded = stripped.addingPercentEncoding(withAllowedCharacters: .urlQueryAllowed) ?? stripped
        return "https://images.weserv.nl/?url=\(encoded)"
    }
}

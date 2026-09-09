import Foundation
import UIKit

/// Talks to the `disqueria/v1` REST routes registered by the WordPress plugin
/// (disqueria-app-plugin.php), authenticating with a WordPress Application Password.
struct WooCommerceAPI {
    let siteUrl: String
    let username: String
    let appPassword: String

    static func normalize(_ raw: String) -> String {
        var v = raw.trimmingCharacters(in: .whitespacesAndNewlines)
        while v.hasSuffix("/") { v.removeLast() }
        if !v.isEmpty, !v.lowercased().hasPrefix("http://"), !v.lowercased().hasPrefix("https://") {
            v = "https://" + v
        }
        return v
    }

    private var base: String { Self.normalize(siteUrl) + "/wp-json" }

    private var authPair: String {
        username.trimmingCharacters(in: .whitespacesAndNewlines) + ":" + appPassword.replacingOccurrences(of: #"\s+"#, with: "", options: .regularExpression)
    }

    private var authHeaders: [String: String] {
        let encoded = Data(authPair.utf8).base64EncodedString()
        return ["Authorization": "Basic \(encoded)", "X-Disqueria-Auth": encoded]
    }

    // MARK: Site info (public, no auth required)

    func siteInfo() async throws -> SiteInfo {
        guard let url = URL(string: base + "/disqueria/v1/site-info") else {
            throw APIError(message: "URL de tienda inválida")
        }
        return try await APIClient.request(url: url, as: SiteInfo.self)
    }

    // MARK: Categories

    func categories() async throws -> [WooCategory] {
        guard let url = URL(string: base + "/disqueria/v1/categories") else {
            throw APIError(message: "URL de tienda inválida")
        }
        return try await APIClient.request(url: url, headers: authHeaders, as: [WooCategory].self)
    }

    func createCategory(name: String) async throws -> WooCategory {
        guard let url = URL(string: base + "/disqueria/v1/categories") else {
            throw APIError(message: "URL de tienda inválida")
        }
        return try await APIClient.request(url: url, method: .post, headers: authHeaders, jsonBody: ["name": name], as: WooCategory.self)
    }

    // MARK: Tags

    func resolveTag(name: String) async throws -> Int {
        guard let url = URL(string: base + "/disqueria/v1/resolve-tag") else {
            throw APIError(message: "URL de tienda inválida")
        }
        let res = try await APIClient.request(url: url, method: .post, headers: authHeaders, jsonBody: ["name": name], as: ResolveTagResponse.self)
        return res.id
    }

    // MARK: Media

    func uploadMedia(image: UIImage, filename: String) async throws -> String {
        guard let url = URL(string: base + "/disqueria/v1/media") else {
            throw APIError(message: "URL de tienda inválida")
        }
        guard let jpeg = image.jpegData(compressionQuality: 0.85) else {
            throw APIError(message: "No se pudo preparar la imagen")
        }
        var headers = authHeaders
        headers["Content-Disposition"] = "attachment; filename=\"\(filename)\""
        headers["Content-Type"] = "image/jpeg"
        let res = try await APIClient.request(url: url, method: .post, headers: headers, rawBody: jpeg, as: MediaUploadResponse.self)
        return res.sourceUrl
    }

    func uploadMedia(remoteUrl: String, filename: String) async throws -> String {
        guard let src = URL(string: remoteUrl) else { throw APIError(message: "URL de imagen inválida") }
        let (data, response) = try await URLSession.shared.data(from: src)
        guard let http = response as? HTTPURLResponse, (200...299).contains(http.statusCode) else {
            throw APIError(message: "No se pudo descargar la imagen de portada")
        }
        guard let url = URL(string: base + "/disqueria/v1/media") else {
            throw APIError(message: "URL de tienda inválida")
        }
        var headers = authHeaders
        headers["Content-Disposition"] = "attachment; filename=\"\(filename)\""
        headers["Content-Type"] = http.mimeType ?? "image/jpeg"
        let res = try await APIClient.request(url: url, method: .post, headers: headers, rawBody: data, as: MediaUploadResponse.self)
        return res.sourceUrl
    }

    // MARK: Products

    func products() async throws -> [WooProductSummary] {
        guard let url = URL(string: base + "/disqueria/v1/products") else {
            throw APIError(message: "URL de tienda inválida")
        }
        return try await APIClient.request(url: url, headers: authHeaders, as: [WooProductSummary].self)
    }

    func createProduct(body: [String: Any]) async throws -> WooProductCreateResponse {
        guard let url = URL(string: base + "/disqueria/v1/products") else {
            throw APIError(message: "URL de tienda inválida")
        }
        return try await APIClient.request(url: url, method: .post, headers: authHeaders, jsonBody: body, as: WooProductCreateResponse.self)
    }

    func updateProduct(id: Int, body: [String: Any]) async throws {
        guard let url = URL(string: base + "/disqueria/v1/products/\(id)") else {
            throw APIError(message: "URL de tienda inválida")
        }
        _ = try await APIClient.request(url: url, method: .put, headers: authHeaders, jsonBody: body, as: WooProductUpdateResponse.self)
    }

    func deleteProduct(id: Int) async throws {
        guard let url = URL(string: base + "/disqueria/v1/products/\(id)") else {
            throw APIError(message: "URL de tienda inválida")
        }
        _ = try await APIClient.request(url: url, method: .delete, headers: authHeaders, as: WooProductUpdateResponse.self)
    }
}

import Foundation

struct APIError: LocalizedError {
    let message: String
    var errorDescription: String? { message }
}

enum HTTPMethod: String {
    case get = "GET", post = "POST", put = "PUT", delete = "DELETE"
}

/// Thin JSON REST helper shared by the WooCommerce and Discogs clients.
enum APIClient {
    static func request<T: Decodable>(
        url: URL,
        method: HTTPMethod = .get,
        headers: [String: String] = [:],
        jsonBody: [String: Any]? = nil,
        rawBody: Data? = nil,
        as type: T.Type
    ) async throws -> T {
        var req = URLRequest(url: url)
        req.httpMethod = method.rawValue
        for (k, v) in headers { req.setValue(v, forHTTPHeaderField: k) }
        if let jsonBody {
            req.setValue("application/json", forHTTPHeaderField: "Content-Type")
            req.httpBody = try JSONSerialization.data(withJSONObject: jsonBody)
        } else if let rawBody {
            req.httpBody = rawBody
        }

        let (data, response) = try await URLSession.shared.data(for: req)
        guard let http = response as? HTTPURLResponse else {
            throw APIError(message: "Respuesta inválida del servidor")
        }
        guard (200...299).contains(http.statusCode) else {
            let decoded = try? JSONDecoder().decode(WPErrorResponse.self, from: data)
            let text = String(data: data, encoding: .utf8) ?? ""
            throw APIError(message: decoded?.message ?? (text.isEmpty ? "Error \(http.statusCode)" : text))
        }
        do {
            return try JSONDecoder().decode(T.self, from: data)
        } catch {
            throw APIError(message: "No se pudo interpretar la respuesta del servidor: \(error.localizedDescription)")
        }
    }
}

/// Decodable wrapper for endpoints that legitimately return "no content" style JSON we don't need to parse.
struct EmptyResponse: Decodable {}

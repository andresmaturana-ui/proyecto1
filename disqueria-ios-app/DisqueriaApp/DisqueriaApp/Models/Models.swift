import Foundation

// MARK: - Discogs

struct DiscogsSearchResponse: Decodable {
    let results: [DiscogsSearchItem]?
}

struct DiscogsSearchItem: Decodable, Identifiable {
    let id: Int
    let title: String?
    let thumb: String?
    let coverImage: String?
    let year: String?
    let label: [String]?
    let format: [String]?
    let country: String?

    enum CodingKeys: String, CodingKey {
        case id, title, thumb, year, label, format, country
        case coverImage = "cover_image"
    }

    var displayTitle: String { title ?? "Sin título" }
    var thumbUrl: String? { thumb?.isEmpty == false ? thumb : (coverImage?.isEmpty == false ? coverImage : nil) }
    var metaLine: String {
        [year, label?.first, format?.joined(separator: "/"), country]
            .compactMap { $0 }
            .filter { !$0.isEmpty }
            .joined(separator: " · ")
    }
}

struct DiscogsRelease: Decodable {
    let id: Int?
    let title: String?
    let year: Int?
    let country: String?
    let notes: String?
    let genres: [String]?
    let styles: [String]?
    let artists: [DiscogsArtist]?
    let labels: [DiscogsLabel]?
    let formats: [DiscogsFormat]?
    let tracklist: [DiscogsTrack]?
    let images: [DiscogsImage]?

    var artistNames: String {
        (artists ?? []).map { $0.name.replacingOccurrences(of: #"\s*\(\d+\)$"#, with: "", options: .regularExpression) }.joined(separator: ", ")
    }
    var labelNames: String { (labels ?? []).map { $0.name }.joined(separator: ", ") }
    var genreStyleList: [String] {
        var seen = Set<String>()
        var out: [String] = []
        for v in (genres ?? []) + (styles ?? []) where !seen.contains(v) {
            seen.insert(v); out.append(v)
        }
        return out
    }
    var genreStyleString: String { genreStyleList.joined(separator: ", ") }
    var formatString: String { (formats ?? []).map { $0.name }.joined(separator: ", ") }
    var trackNames: String {
        (tracklist ?? []).map { t -> String in
            if let pos = t.position, !pos.isEmpty { return "\(pos). \(t.title)" }
            return t.title
        }.joined(separator: "\n")
    }
    var coverImageUrl: String? { images?.first?.uri ?? images?.first?.resourceUrl }
}

struct DiscogsArtist: Decodable { let name: String }
struct DiscogsLabel: Decodable { let name: String }
struct DiscogsFormat: Decodable { let name: String }
struct DiscogsTrack: Decodable {
    let position: String?
    let title: String
}
struct DiscogsImage: Decodable {
    let uri: String?
    let resourceUrl: String?
    enum CodingKeys: String, CodingKey { case uri; case resourceUrl = "resource_url" }
}

// MARK: - Store (WooCommerce / disqueria plugin REST)

struct SiteInfo: Decodable {
    let name: String?
    let logo: String?
}

struct WooCategory: Codable, Identifiable, Equatable {
    let id: Int
    let name: String
}

struct WooProductSummary: Decodable, Identifiable {
    let id: Int
    let name: String?
    let regularPrice: String?
    let stockQuantity: Int?
    let featuredImage: String?

    enum CodingKeys: String, CodingKey {
        case id, name
        case regularPrice = "regular_price"
        case stockQuantity = "stock_quantity"
        case featuredImage = "featured_image"
    }
}

struct WooProductCreateResponse: Decodable {
    let id: Int
    let name: String?
    let permalink: String?
}

struct WooProductUpdateResponse: Decodable {
    let ok: Bool?
    let id: Int?
}

struct MediaUploadResponse: Decodable {
    let id: Int
    let sourceUrl: String
    enum CodingKeys: String, CodingKey { case id; case sourceUrl = "source_url" }
}

struct ResolveTagResponse: Decodable {
    let id: Int
}

struct WPErrorResponse: Decodable {
    let code: String?
    let message: String?
}

// MARK: - App-level small structs

struct CategoryGuess {
    let name: String
    var category: WooCategory?
}

enum GoldmineGrade: String, CaseIterable, Identifiable {
    case m = "M", nm = "NM", vgPlus = "VG+", vg = "VG", gPlus = "G+", g = "G"
    var id: String { rawValue }
    var label: String {
        switch self {
        case .m: return "Mint"
        case .nm: return "Near Mint"
        case .vgPlus: return "Very Good Plus"
        case .vg: return "Very Good"
        case .gPlus: return "Good Plus"
        case .g: return "Good"
        }
    }
}

struct FieldDef: Identifiable {
    let key: String
    let label: String
    var id: String { key }

    static let all: [FieldDef] = [
        FieldDef(key: "fTitle", label: "Título"),
        FieldDef(key: "fArtist", label: "Artista"),
        FieldDef(key: "fYear", label: "Año"),
        FieldDef(key: "fLabel", label: "Sello discográfico"),
        FieldDef(key: "fCountry", label: "País"),
        FieldDef(key: "fGenre", label: "Género/Estilo"),
        FieldDef(key: "fFormat", label: "Formato"),
        FieldDef(key: "fTracklist", label: "Tracklist"),
        FieldDef(key: "fCover", label: "Portada/imagen"),
        FieldDef(key: "fNotes", label: "Notas del release"),
    ]
}

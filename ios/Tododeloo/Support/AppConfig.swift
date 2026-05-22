import Foundation

/// Central place for runtime configuration. The API base URL defaults to the
/// local Herd domain but can be overridden at runtime (e.g. to point at a
/// deployed Laravel Cloud instance) via Settings or UserDefaults.
enum AppConfig {
    static let defaultBaseURL = "https://tododeloo.on-forge.com"

    private static let baseURLKey = "api_base_url"

    static var apiBaseURL: URL {
        let stored = UserDefaults.standard.string(forKey: baseURLKey)
        let raw = (stored?.isEmpty == false) ? stored! : defaultBaseURL
        return URL(string: raw) ?? URL(string: defaultBaseURL)!
    }

    static func setBaseURL(_ value: String) {
        let trimmed = value.trimmingCharacters(in: .whitespacesAndNewlines)
        UserDefaults.standard.set(trimmed, forKey: baseURLKey)
    }
}

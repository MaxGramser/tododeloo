import SwiftUI
import UIKit

/// Editorial Swiss palette: off-white paper, black ink, a single orange accent.
/// Adapts to dark mode so text stays legible in both appearances.
enum Theme {
    static let background = Color(light: Color(red: 0.96, green: 0.95, blue: 0.93), dark: Color(red: 0.07, green: 0.07, blue: 0.07))
    static let surface = Color(light: Color(red: 0.99, green: 0.985, blue: 0.97), dark: Color(red: 0.13, green: 0.13, blue: 0.13))
    static let ink = Color(light: .black, dark: Color(red: 0.93, green: 0.92, blue: 0.90))
    static let accent = Color(red: 0.949, green: 0.420, blue: 0.149)
    static let muted = Color(light: .black.opacity(0.55), dark: .white.opacity(0.65))
    static let faint = Color(light: .black.opacity(0.38), dark: .white.opacity(0.45))
    static let hairline = Color(light: .black.opacity(0.12), dark: .white.opacity(0.16))
}

extension Color {
    /// A color that resolves differently in light and dark appearance.
    init(light: Color, dark: Color) {
        self = Color(uiColor: UIColor { traits in
            traits.userInterfaceStyle == .dark ? UIColor(dark) : UIColor(light)
        })
    }
}

extension Font {
    /// Heavy display sans for headlines.
    static func display(_ size: CGFloat) -> Font {
        .system(size: size, weight: .black, design: .default)
    }

    /// Monospaced labels for the editorial captions.
    static func mono(_ size: CGFloat, weight: Font.Weight = .medium) -> Font {
        .system(size: size, weight: weight, design: .monospaced)
    }
}

/// Uppercase monospaced caption used throughout the UI.
struct MonoLabel: View {
    let text: String
    var color: Color

    init(_ text: String, color: Color = Theme.muted) {
        self.text = text
        self.color = color
    }

    var body: some View {
        Text(text.uppercased())
            .font(.mono(11, weight: .semibold))
            .tracking(1.6)
            .foregroundStyle(color)
    }
}

/// The signature orange dot.
struct AccentDot: View {
    var size: CGFloat = 8
    var body: some View {
        Circle()
            .fill(Theme.accent)
            .frame(width: size, height: size)
    }
}

struct PrimaryButtonStyle: ButtonStyle {
    func makeBody(configuration: Configuration) -> some View {
        configuration.label
            .font(.system(size: 16, weight: .bold))
            .foregroundStyle(Theme.background)
            .frame(maxWidth: .infinity)
            .padding(.vertical, 16)
            .background(Theme.ink)
            .opacity(configuration.isPressed ? 0.7 : 1)
    }
}

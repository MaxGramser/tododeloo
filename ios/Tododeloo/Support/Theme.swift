import SwiftUI

/// Editorial Swiss palette: off-white paper, black ink, a single orange accent.
enum Theme {
    static let background = Color(red: 0.96, green: 0.95, blue: 0.93)
    static let surface = Color(red: 0.99, green: 0.985, blue: 0.97)
    static let ink = Color.black
    static let accent = Color(red: 0.949, green: 0.420, blue: 0.149)
    static let muted = Color.black.opacity(0.45)
    static let faint = Color.black.opacity(0.30)
    static let hairline = Color.black.opacity(0.12)
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

import SwiftUI

extension Color {
    /// Parses `#rrggbb` (or `rrggbb`) hex strings; nil for anything else.
    init?(hexString: String?) {
        guard var string = hexString?.trimmingCharacters(in: .whitespaces), !string.isEmpty else {
            return nil
        }
        if string.hasPrefix("#") {
            string.removeFirst()
        }
        guard string.count == 6, let value = Int(string, radix: 16) else {
            return nil
        }
        let red = Double((value >> 16) & 0xFF) / 255
        let green = Double((value >> 8) & 0xFF) / 255
        let blue = Double(value & 0xFF) / 255
        self = Color(red: red, green: green, blue: blue)
    }
}

/// Date string helpers. The API speaks `yyyy-MM-dd`; the UI speaks Dutch.
enum DateText {
    static func parse(_ ymd: String) -> Date? {
        let formatter = DateFormatter()
        formatter.locale = Locale(identifier: "en_US_POSIX")
        formatter.dateFormat = "yyyy-MM-dd"
        return formatter.date(from: ymd)
    }

    static func ymd(_ date: Date) -> String {
        let formatter = DateFormatter()
        formatter.locale = Locale(identifier: "en_US_POSIX")
        formatter.dateFormat = "yyyy-MM-dd"
        return formatter.string(from: date)
    }

    static func long(_ ymd: String) -> String {
        guard let date = parse(ymd) else { return ymd }
        let formatter = DateFormatter()
        formatter.locale = Locale(identifier: "nl_NL")
        formatter.dateFormat = "EEEE d MMMM"
        return formatter.string(from: date)
    }
}

/// The tappable completion circle. Tap toggles done; high-priority todos show
/// an accent ring even when open.
struct CompletionToggle: View {
    let isCompleted: Bool
    let priority: Priority

    var body: some View {
        ZStack {
            Circle()
                .strokeBorder(isCompleted ? Theme.accent : ringColor, lineWidth: 2)
                .frame(width: 24, height: 24)
            if isCompleted {
                Circle()
                    .fill(Theme.accent)
                    .frame(width: 13, height: 13)
            }
        }
    }

    private var ringColor: Color {
        priority == .high ? Theme.accent : Theme.faint
    }
}

struct TagChip: View {
    let tag: Tag

    var body: some View {
        Text(tag.name.lowercased())
            .font(.mono(10, weight: .semibold))
            .padding(.horizontal, 6)
            .padding(.vertical, 2)
            .background(color.opacity(0.16))
            .foregroundStyle(color)
            .clipShape(Capsule())
    }

    private var color: Color {
        Color(hexString: tag.color) ?? Theme.ink
    }
}

struct UnderlinedField: View {
    let placeholder: String
    @Binding var text: String
    var isSecure = false
    var submitLabel: SubmitLabel = .done
    var onSubmit: () -> Void = {}

    var body: some View {
        VStack(alignment: .leading, spacing: 8) {
            Group {
                if isSecure {
                    SecureField(placeholder, text: $text)
                } else {
                    TextField(placeholder, text: $text)
                }
            }
            .font(.system(size: 18, weight: .medium))
            .foregroundStyle(Theme.ink)
            .submitLabel(submitLabel)
            .onSubmit(onSubmit)
            Rectangle()
                .fill(Theme.hairline)
                .frame(height: 1.5)
        }
    }
}

struct EmptyStateView: View {
    let headline: String
    let subtitle: String

    var body: some View {
        VStack(alignment: .leading, spacing: 12) {
            AccentDot(size: 12)
            Text(headline)
                .font(.display(26))
                .foregroundStyle(Theme.ink)
            Text(subtitle)
                .font(.system(size: 15))
                .foregroundStyle(Theme.muted)
        }
        .frame(maxWidth: .infinity, alignment: .leading)
        .padding(.horizontal, 20)
        .padding(.top, 40)
    }
}

/// Consistent chrome for the small editorial sheets.
struct SheetScaffold<Content: View>: View {
    let title: String
    @ViewBuilder var content: () -> Content

    var body: some View {
        VStack(alignment: .leading, spacing: 24) {
            HStack(spacing: 8) {
                MonoLabel(title)
                AccentDot(size: 6)
            }
            content()
            Spacer(minLength: 0)
        }
        .padding(24)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(Theme.background)
        .presentationDragIndicator(.visible)
    }
}

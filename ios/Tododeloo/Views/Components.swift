import SwiftUI
#if canImport(UIKit)
import UIKit
#endif

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

    /// ISO date `days` from today — `offset(1)` is tomorrow, `offset(7)` a week out.
    static func offset(_ days: Int) -> String {
        let date = Calendar(identifier: .gregorian).date(byAdding: .day, value: days, to: Date()) ?? Date()
        return ymd(date)
    }

    static func long(_ ymd: String) -> String {
        guard let date = parse(ymd) else { return ymd }
        let formatter = DateFormatter()
        formatter.locale = Locale(identifier: "nl_NL")
        formatter.dateFormat = "EEEE d MMMM"
        return formatter.string(from: date)
    }

    /// Short relative-friendly date for a header title, e.g. "Ma 25 mei".
    static func medium(_ ymd: String) -> String {
        guard let date = parse(ymd) else { return ymd }
        let formatter = DateFormatter()
        formatter.locale = Locale(identifier: "nl_NL")
        formatter.dateFormat = "EEE d MMM"
        return formatter.string(from: date).capitalized
    }

    /// "Vandaag" / "Morgen" / "Gisteren" when the date is near, else the medium
    /// date ("Wo 27 mei"). Used to label when a todo becomes relevant.
    static func relative(_ ymd: String) -> String {
        guard let date = parse(ymd) else { return ymd }
        let calendar = Calendar(identifier: .gregorian)
        let days = calendar.dateComponents(
            [.day],
            from: calendar.startOfDay(for: Date()),
            to: calendar.startOfDay(for: date)
        ).day ?? 0
        switch days {
        case 0: return "Vandaag"
        case 1: return "Morgen"
        case -1: return "Gisteren"
        default: return medium(ymd)
        }
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
                    // A springy fill on check-off, à la Reminders.
                    .transition(.scale.combined(with: .opacity))
            }
        }
        .animation(.spring(response: 0.32, dampingFraction: 0.58), value: isCompleted)
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

// MARK: - Quick-add parse preview

/// A live strip under a quick-add field: shows the typed sentence coloured by
/// how it will be parsed (date/recurrence in accent, the task in ink, filler
/// dimmed) plus the resolved date. Debounced; reuses the shared API. Drop it
/// under any quick-add field on iOS and Mac. On Mac, hovering shows the resolved
/// value as a tooltip.
struct ParsePreviewStrip: View {
    /// `.plain` floats the preview on its own; `.attached` draws a top hairline +
    /// inner padding so it reads as the bottom half of the quick-add card it sits in.
    enum Style { case plain, attached }

    let text: String
    var style: Style = .plain
    /// When false (raw mode) the backend returns the whole line as one plain
    /// title segment, so the strip mirrors what will actually be saved.
    var parse: Bool = true
    @State private var preview: ParsePreview?

    var body: some View {
        Group {
            if let preview, !preview.segments.isEmpty {
                content(preview)
            } else {
                // A concrete, zero-height placeholder. SwiftUI never "appears" an
                // empty view, so `.task` would never fire — and the strip would
                // stay invisible forever. This keeps a real view in the layout so
                // the debounced fetch actually runs.
                Color.clear.frame(height: 0)
            }
        }
        .animation(.snappy(duration: 0.2), value: preview)
        .task(id: "\(parse)\u{1}\(text)") { await refresh() }
    }

    private func refresh() async {
        let trimmed = text.trimmingCharacters(in: .whitespacesAndNewlines)
        guard !trimmed.isEmpty, APIClient.shared.isAuthenticated else {
            preview = nil
            return
        }
        // Debounce: this task is cancelled and restarted on every keystroke.
        try? await Task.sleep(for: .milliseconds(220))
        guard !Task.isCancelled else { return }
        let result = try? await APIClient.shared.parsePreview(trimmed, parse: parse)
        if !Task.isCancelled {
            preview = result
        }
    }

    @ViewBuilder
    private func content(_ preview: ParsePreview) -> some View {
        switch style {
        case .plain:
            row(preview, showsEyebrow: true)
        case .attached:
            VStack(spacing: 0) {
                Rectangle().fill(Theme.hairline).frame(height: 1)
                row(preview, showsEyebrow: false)
                    .padding(.horizontal, 12)
                    .padding(.vertical, 9)
            }
        }
    }

    private func row(_ preview: ParsePreview, showsEyebrow: Bool) -> some View {
        VStack(alignment: .leading, spacing: 7) {
            HStack(alignment: .firstTextBaseline, spacing: 8) {
                if showsEyebrow {
                    MonoLabel(parse ? "leest als" : "letterlijk", color: Theme.faint)
                }
                Text(attributed(preview.segments))
                    .font(.system(size: 13))
                    .lineLimit(3)
                    .multilineTextAlignment(.leading)
                Spacer(minLength: 0)
            }

            // The resolved date/recurrence on its own full-width line, so a long
            // recurrence ("Elke dinsdag, woensdag … · vanaf di 26 mei") shows in
            // full instead of being squeezed into a truncated pill.
            if let label = resolvedLabel(preview) {
                HStack(spacing: 5) {
                    Image(systemName: preview.recurrence != nil ? "repeat" : "calendar")
                        .font(.system(size: 9, weight: .bold))
                    Text(label)
                        .font(.mono(10, weight: .semibold))
                        .lineLimit(2)
                        .multilineTextAlignment(.leading)
                }
                .foregroundStyle(Theme.accent)
                .padding(.horizontal, 8)
                .padding(.vertical, 4)
                .background(Theme.accent.opacity(0.13), in: RoundedRectangle(cornerRadius: 7, style: .continuous))
                .help(label)
            }
        }
        .frame(maxWidth: .infinity, alignment: .leading)
    }

    private func attributed(_ segments: [ParseSegment]) -> AttributedString {
        var result = AttributedString()
        for segment in segments {
            var piece = AttributedString(segment.text)
            switch segment.type {
            case "date", "recurrence":
                piece.foregroundColor = Theme.accent
                piece.font = .system(size: 14, weight: .semibold)
            case "title":
                piece.foregroundColor = Theme.ink
            default:
                piece.foregroundColor = Theme.faint
            }
            result += piece
        }
        return result
    }

    private func resolvedLabel(_ preview: ParsePreview) -> String? {
        if let date = preview.date {
            return date.label
        }
        if let recurrence = preview.recurrence {
            return "\(recurrence.summary) · vanaf \(recurrence.anchorLabel)"
        }
        return nil
    }
}

/// A tiny mono toggle for the quick-add field: "auto" (date/recurrence parsing
/// on) vs "letterlijk" (raw — store the line as typed). Matches the web toggle.
struct ParseModeToggle: View {
    @Binding var parsing: Bool

    var body: some View {
        Button {
            parsing.toggle()
        } label: {
            MonoLabel(parsing ? "auto" : "letterlijk", color: parsing ? Theme.faint : Theme.accent)
        }
        .buttonStyle(.plain)
        .help(parsing
            ? "Datum en herhaling worden herkend — tik om letterlijk op te slaan"
            : "Wordt letterlijk opgeslagen — tik om herkenning aan te zetten")
    }
}

// MARK: - Toast

/// A transient confirmation banner. Reusable across the app: call
/// `ToastCenter.shared.show(...)` from anywhere and host the UI once near the
/// app root with `.toastHost()`. First used to surface what the quick-add
/// parser scheduled ("ingepland voor volgende week dinsdag").
struct ToastMessage: Identifiable, Equatable {
    let id = UUID()
    let message: String
    let detail: String?
}

@MainActor
@Observable
final class ToastCenter {
    static let shared = ToastCenter()

    private(set) var current: ToastMessage?
    private var dismissTask: Task<Void, Never>?

    func show(_ message: String, detail: String? = nil) {
        #if canImport(UIKit)
        UINotificationFeedbackGenerator().notificationOccurred(.success)
        #endif
        current = ToastMessage(message: message, detail: detail)
        dismissTask?.cancel()
        dismissTask = Task { [weak self] in
            try? await Task.sleep(for: .seconds(3))
            if !Task.isCancelled { self?.current = nil }
        }
    }

    func dismiss() {
        dismissTask?.cancel()
        dismissTask = nil
        current = nil
    }
}

private struct ToastBanner: View {
    let toast: ToastMessage

    var body: some View {
        HStack(alignment: .top, spacing: 12) {
            AccentDot(size: 8)
                .padding(.top, 5)
            VStack(alignment: .leading, spacing: 2) {
                Text(toast.message)
                    .font(.system(size: 15, weight: .semibold))
                    .foregroundStyle(Theme.ink)
                if let detail = toast.detail {
                    Text(detail)
                        .font(.system(size: 13))
                        .foregroundStyle(Theme.muted)
                }
            }
            Spacer(minLength: 0)
        }
        .padding(.horizontal, 16)
        .padding(.vertical, 13)
        .background(Theme.surface)
        .clipShape(RoundedRectangle(cornerRadius: 14, style: .continuous))
        .overlay(
            RoundedRectangle(cornerRadius: 14, style: .continuous)
                .strokeBorder(Theme.hairline, lineWidth: 1)
        )
        .shadow(color: .black.opacity(0.14), radius: 18, y: 8)
        .frame(maxWidth: 440)
    }
}

private struct ToastHostModifier: ViewModifier {
    @State private var toasts = ToastCenter.shared

    func body(content: Content) -> some View {
        content.overlay(alignment: .bottom) {
            if let toast = toasts.current {
                ToastBanner(toast: toast)
                    .padding(.horizontal, 20)
                    .padding(.bottom, 24)
                    .transition(.move(edge: .bottom).combined(with: .opacity))
                    .onTapGesture { toasts.dismiss() }
            }
        }
        .animation(.spring(response: 0.32, dampingFraction: 0.82), value: toasts.current)
    }
}

extension View {
    /// Hosts the shared `ToastCenter` UI. Attach once near the app root.
    func toastHost() -> some View {
        modifier(ToastHostModifier())
    }
}

// MARK: - Sub-task progress

/// A circular progress ring for a todo's sub-tasks. Mirrors the web's
/// SubProgressRing: a faint track, an accent arc as they get done, and a filled
/// accent disc with a check once they're all complete. Used in place of the
/// completion toggle on a todo that has sub-tasks — you finish the subs, not the
/// parent.
struct SubProgressRing: View {
    let done: Int
    let total: Int
    var size: CGFloat = 20

    private var progress: Double { total == 0 ? 0 : Double(done) / Double(total) }
    private var isComplete: Bool { done > 0 && done == total }

    var body: some View {
        ZStack {
            Circle()
                .strokeBorder(Theme.faint.opacity(0.55), lineWidth: 2)
            if isComplete {
                Circle().fill(Theme.accent)
                Image(systemName: "checkmark")
                    .font(.system(size: size * 0.46, weight: .bold))
                    .foregroundStyle(Theme.background)
            } else if progress > 0 {
                Circle()
                    .trim(from: 0, to: progress)
                    .stroke(Theme.accent, style: StrokeStyle(lineWidth: 2, lineCap: .round))
                    .rotationEffect(.degrees(-90))
            }
        }
        .frame(width: size, height: size)
        .animation(.easeInOut(duration: 0.25), value: progress)
    }
}

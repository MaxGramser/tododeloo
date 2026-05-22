import SwiftUI

/// Routes for the sheets a board can present.
enum TodoSheet: Identifiable {
    case detail(Todo)
    case rename(Todo)
    case move(Todo)
    case addSub(Todo)
    case customRecurrence(Todo)

    var id: String {
        switch self {
        case .detail(let todo): return "detail-\(todo.id)"
        case .rename(let todo): return "rename-\(todo.id)"
        case .move(let todo): return "move-\(todo.id)"
        case .addSub(let todo): return "addsub-\(todo.id)"
        case .customRecurrence(let todo): return "recurrence-\(todo.id)"
        }
    }
}

struct RenameSheet: View {
    @Environment(\.dismiss) private var dismiss
    let initialTitle: String
    let onSave: (String) -> Void
    @State private var text: String

    init(initialTitle: String, onSave: @escaping (String) -> Void) {
        self.initialTitle = initialTitle
        self.onSave = onSave
        _text = State(initialValue: initialTitle)
    }

    var body: some View {
        SheetScaffold(title: "Hernoemen") {
            UnderlinedField(placeholder: "Titel", text: $text) { save() }
            Button("Bewaren", action: save)
                .buttonStyle(PrimaryButtonStyle())
        }
        .presentationDetents([.height(220)])
    }

    private func save() {
        onSave(text)
        dismiss()
    }
}

struct AddSubTodoSheet: View {
    @Environment(\.dismiss) private var dismiss
    let onAdd: (String) -> Void
    @State private var text = ""

    var body: some View {
        SheetScaffold(title: "Subtaak") {
            UnderlinedField(placeholder: "Wat is de subtaak?", text: $text) { add() }
            Button("Toevoegen", action: add)
                .buttonStyle(PrimaryButtonStyle())
        }
        .presentationDetents([.height(220)])
    }

    private func add() {
        onAdd(text)
        dismiss()
    }
}

struct MoveDateSheet: View {
    @Environment(\.dismiss) private var dismiss
    let onPick: (String) -> Void
    @State private var date = Date()

    var body: some View {
        SheetScaffold(title: "Verplaats naar") {
            DatePicker("Datum", selection: $date, displayedComponents: .date)
                .datePickerStyle(.graphical)
                .tint(Theme.accent)
                .labelsHidden()
            Button("Verplaatsen") {
                onPick(DateText.ymd(date))
                dismiss()
            }
            .buttonStyle(PrimaryButtonStyle())
        }
        .presentationDetents([.medium, .large])
    }
}

struct CreateListSheet: View {
    @Environment(\.dismiss) private var dismiss
    let onCreate: (String) -> Void
    @State private var text = ""

    var body: some View {
        SheetScaffold(title: "Nieuwe lijst") {
            UnderlinedField(placeholder: "Naam van de lijst", text: $text) { create() }
            Button("Aanmaken", action: create)
                .buttonStyle(PrimaryButtonStyle())
        }
        .presentationDetents([.height(220)])
    }

    private func create() {
        onCreate(text)
        dismiss()
    }
}

/// Builds an arbitrary RFC 5545 RRULE (every N days/weeks/months/years, weekday
/// picks, monthly-on-the-Nth-weekday). Anchored on the day the todo sits on.
struct RecurrenceSheet: View {
    @Environment(\.dismiss) private var dismiss
    let anchorISO: String
    let onSave: (String) -> Void

    enum Freq: String, CaseIterable, Identifiable {
        case daily, weekly, monthly, yearly
        var id: String { rawValue }

        func label(plural: Bool) -> String {
            switch self {
            case .daily: return plural ? "dagen" : "dag"
            case .weekly: return plural ? "weken" : "week"
            case .monthly: return plural ? "maanden" : "maand"
            case .yearly: return plural ? "jaren" : "jaar"
            }
        }

        var rfc: String {
            switch self {
            case .daily: return "DAILY"
            case .weekly: return "WEEKLY"
            case .monthly: return "MONTHLY"
            case .yearly: return "YEARLY"
            }
        }
    }

    private let weekdayCodes = ["MO", "TU", "WE", "TH", "FR", "SA", "SU"]
    private let weekdayLabels = ["Ma", "Di", "Wo", "Do", "Vr", "Za", "Zo"]
    private let weekdayNames = ["zondag", "maandag", "dinsdag", "woensdag", "donderdag", "vrijdag", "zaterdag"]
    private let ordinals = ["", "1e", "2e", "3e", "4e"]

    @State private var freq: Freq = .weekly
    @State private var interval = 1
    @State private var days: Set<String> = []
    @State private var monthlyWeekday = true

    var body: some View {
        SheetScaffold(title: "Herhaal") {
            Picker("Frequentie", selection: $freq) {
                ForEach(Freq.allCases) { Text($0.label(plural: false).capitalized).tag($0) }
            }
            .pickerStyle(.segmented)

            Stepper("Elke \(interval) \(freq.label(plural: interval > 1))", value: $interval, in: 1...99)
                .font(.system(size: 15, weight: .medium))

            if freq == .weekly {
                HStack(spacing: 6) {
                    ForEach(Array(zip(weekdayCodes, weekdayLabels)), id: \.0) { code, label in
                        Button {
                            toggle(code)
                        } label: {
                            Text(label)
                                .font(.system(size: 13, weight: .semibold))
                                .frame(width: 38, height: 38)
                                .background(Circle().fill(days.contains(code) ? Theme.accent : Color.clear))
                                .foregroundStyle(days.contains(code) ? Theme.background : Theme.muted)
                                .overlay(Circle().stroke(days.contains(code) ? Color.clear : Theme.faint, lineWidth: 1))
                        }
                        .buttonStyle(.plain)
                    }
                }
            }

            if freq == .monthly {
                VStack(spacing: 8) {
                    monthlyOption("Op de \(nthLabel) \(weekdayName)", selected: monthlyWeekday) { monthlyWeekday = true }
                    monthlyOption("Op dag \(dayOfMonth) van de maand", selected: !monthlyWeekday) { monthlyWeekday = false }
                }
            }

            Button("Bewaar herhaling") {
                onSave(rrule)
                dismiss()
            }
            .buttonStyle(PrimaryButtonStyle())
        }
        .presentationDetents([.medium, .large])
        .onAppear { days = [anchorCode] }
    }

    private func monthlyOption(_ title: String, selected: Bool, action: @escaping () -> Void) -> some View {
        Button(action: action) {
            HStack(spacing: 12) {
                Circle()
                    .fill(selected ? Theme.accent : Theme.faint.opacity(0.4))
                    .frame(width: 8, height: 8)
                Text(title)
                    .font(.system(size: 15))
                    .foregroundStyle(Theme.ink)
                Spacer()
            }
            .padding(.horizontal, 14)
            .padding(.vertical, 12)
            .overlay(
                RoundedRectangle(cornerRadius: 10)
                    .stroke(selected ? Theme.accent : Theme.faint, lineWidth: 1)
            )
        }
        .buttonStyle(.plain)
    }

    private func toggle(_ code: String) {
        if days.contains(code) {
            days.remove(code)
        } else {
            days.insert(code)
        }
    }

    // MARK: - Anchor helpers

    private var anchorDate: Date {
        RecurrencePresetOption.dayFormatter.date(from: anchorISO) ?? Date()
    }

    private var anchorCode: String {
        let weekday = Calendar(identifier: .gregorian).component(.weekday, from: anchorDate)
        return weekdayCodes[(weekday - 2 + 7) % 7]
    }

    private var dayOfMonth: Int {
        Calendar(identifier: .gregorian).component(.day, from: anchorDate)
    }

    private var nth: Int {
        let n = Int(ceil(Double(dayOfMonth) / 7.0))
        return n >= 5 ? -1 : n
    }

    private var nthLabel: String { nth == -1 ? "laatste" : ordinals[nth] }

    private var weekdayName: String {
        weekdayNames[Calendar(identifier: .gregorian).component(.weekday, from: anchorDate) - 1]
    }

    private var rrule: String {
        var parts = ["FREQ=\(freq.rfc)"]
        if interval > 1 { parts.append("INTERVAL=\(interval)") }

        if freq == .weekly {
            let ordered = weekdayCodes.filter { days.contains($0) }
            parts.append("BYDAY=\((ordered.isEmpty ? [anchorCode] : ordered).joined(separator: ","))")
        }

        if freq == .monthly {
            if monthlyWeekday {
                parts.append("BYDAY=\(nth)\(anchorCode)")
            } else {
                parts.append("BYMONTHDAY=\(dayOfMonth)")
            }
        }

        return parts.joined(separator: ";")
    }
}

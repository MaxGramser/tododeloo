import SwiftUI

/// Routes for the sheets a board can present.
enum TodoSheet: Identifiable {
    case detail(Todo)
    case rename(Todo)
    case move(Todo)
    case addSub(Todo)

    var id: String {
        switch self {
        case .detail(let todo): return "detail-\(todo.id)"
        case .rename(let todo): return "rename-\(todo.id)"
        case .move(let todo): return "move-\(todo.id)"
        case .addSub(let todo): return "addsub-\(todo.id)"
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

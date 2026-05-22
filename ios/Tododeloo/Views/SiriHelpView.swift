import SwiftUI

/// In-app "so this is how you talk to Siri" page. Lists the exact phrases that
/// trigger the quick-add intent and shows the short back-and-forth.
struct SiriHelpView: View {
    @Environment(\.dismiss) private var dismiss
    @Environment(\.openURL) private var openURL

    private let phrases = [
        "Voeg toe aan Tododeloo",
        "Tododeloo toevoegen",
        "Noteer in Tododeloo",
        "Nieuwe taak in Tododeloo",
    ]

    var body: some View {
        NavigationStack {
            ScrollView {
                VStack(alignment: .leading, spacing: 30) {
                    header
                    phrasesSection
                    exampleSection
                    oneShotSection
                }
                .padding(24)
                .padding(.bottom, 24)
            }
            .background(Theme.background.ignoresSafeArea())
            .toolbar {
                ToolbarItem(placement: .topBarTrailing) {
                    Button("Klaar") { dismiss() }
                        .fontWeight(.bold)
                        .tint(Theme.ink)
                }
            }
        }
    }

    private var header: some View {
        VStack(alignment: .leading, spacing: 8) {
            MonoLabel("Met je stem")
            HStack(alignment: .firstTextBaseline, spacing: 8) {
                Text("Siri")
                    .font(.display(40))
                    .foregroundStyle(Theme.ink)
                AccentDot(size: 9)
            }
            Text("Voeg taken toe aan vandaag zonder te typen.")
                .font(.system(size: 15))
                .foregroundStyle(Theme.muted)
        }
    }

    private var phrasesSection: some View {
        VStack(alignment: .leading, spacing: 16) {
            MonoLabel("Zeg een van deze")
            ForEach(phrases, id: \.self) { phrase in
                HStack(alignment: .top, spacing: 12) {
                    AccentDot(size: 7)
                        .padding(.top, 9)
                    Text("\u{201C}Hé Siri, \(phrase)\u{201D}")
                        .font(.system(size: 21, weight: .bold))
                        .foregroundStyle(Theme.ink)
                        .fixedSize(horizontal: false, vertical: true)
                }
            }
            Text("Siri vraagt daarna wat erop moet — zeg gewoon de taak.")
                .font(.system(size: 14))
                .foregroundStyle(Theme.muted)
                .padding(.top, 2)
        }
    }

    private var exampleSection: some View {
        VStack(alignment: .leading, spacing: 12) {
            MonoLabel("Zo klinkt het")
            VStack(alignment: .leading, spacing: 14) {
                dialogLine(role: "Jij", text: "Hé Siri, voeg toe aan Tododeloo")
                dialogLine(role: "Siri", text: "Wat wil je toevoegen?")
                dialogLine(role: "Jij", text: "Administratie")
                dialogLine(role: "Siri", text: "Toegevoegd voor vandaag: administratie.", accent: true)
            }
            .padding(18)
            .frame(maxWidth: .infinity, alignment: .leading)
            .background(Theme.surface)
            .overlay(
                RoundedRectangle(cornerRadius: 10).strokeBorder(Theme.hairline, lineWidth: 1)
            )
        }
    }

    private func dialogLine(role: String, text: String, accent: Bool = false) -> some View {
        VStack(alignment: .leading, spacing: 3) {
            MonoLabel(role, color: accent ? Theme.accent : Theme.faint)
            Text(text)
                .font(.system(size: 16, weight: .medium))
                .foregroundStyle(accent ? Theme.accent : Theme.ink)
                .fixedSize(horizontal: false, vertical: true)
        }
    }

    private var oneShotSection: some View {
        VStack(alignment: .leading, spacing: 12) {
            MonoLabel("In één zin (geavanceerd)")
            Text("iOS laat een app niet zomaar losse woorden uit je zin halen. Wil je tóch in één keer \u{201C}voeg administratie toe aan Tododeloo\u{201D} zeggen, maak dan in de Opdrachten-app een opdracht met de actie \u{201C}Dicteer tekst\u{201D} die \u{201C}Snel toevoegen\u{201D} voedt, en geef die opdracht je eigen zin.")
                .font(.system(size: 14))
                .foregroundStyle(Theme.muted)
                .fixedSize(horizontal: false, vertical: true)
            Button {
                if let url = URL(string: "shortcuts://") {
                    openURL(url)
                }
            } label: {
                Text("Open Opdrachten")
            }
            .buttonStyle(PrimaryButtonStyle())
        }
    }
}

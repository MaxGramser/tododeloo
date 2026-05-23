# Tododeloo — Native platform-integraties & verbeteringen

> Plan / ideeënlijst, 2026-05-23. Doel: van een goede native client naar een app
> die _diep_ in macOS en iOS zit, zonder de editoriële reductie te verraden.
> Alles blijft lopen via de Laravel JSON-API + action classes (dunne controllers),
> en deelt de bestaande Swift-core (Models / Networking / State).

## Uitgangspunten

- **Reductie blijft heilig.** Elke feature is opt-in of onzichtbaar tot je 'm nodig
  hebt. Geen dashboards, geen badges-overal, geen property-soep.
- **Eén bron van waarheid = de API.** Nieuwe schermen/extensies praten met dezelfde
  endpoints; logica in action classes, niet in de client.
- **Shared core, per-platform UI.** Zoals nu: core in `ios/Tododeloo/`, UI per
  platform. Nieuwe extensies (widgets/share/intents) delen de core via een App Group.

## Wat de top-apps in 2025–2026 doen (onderzoeksbasis)

- **TickTick** — menubalk-mini-lijst, systeembrede sneltoets voor "snel toevoegen",
  kalender + native notificaties. Voelt per platform native.
- **Things 3** — premium native gevoel: typografie, animaties, toetsenbord-first,
  géén webwrapper. (Tododeloo zit hier al goed.)
- **Todoist** — sterke natural-language quick-add ("Submit report every last Friday
  at 4 pm #Work") en AI die een grote taak in subtaken hakt.
- **Akiflow** — keyboard-command-center: één inbox, taken naar de agenda slepen
  (time-blocking), snel triëren met sneltoetsen.
- **Sunsama** — begeleid **dagelijks planningsritueel**: review gisteren → kies de
  focus van vandaag → blok je tijd. Plus een avond-/shutdown-moment. (Ons
  ochtendritueel is hiervan een directe verwant.)
- **Amie / Motion** — AI die agenda + taken + mail samenvoegt en automatisch plant.
- **iOS 18/26** — interactieve widgets (≈3× engagement), Control Center-controls +
  Action Button + Lock Screen, Live Activities (refresh elke 5–15 s), App Intents.
- **Notion 3.x (2026)** — AI-agents/Workers, 20+ database-property-types,
  multi-source views, snelle AI-autofill, voice-input.
- **Apple Intelligence (iOS/macOS 26)** — **Foundation Models framework**: een
  on-device LLM die apps mogen aanroepen (geen server nodig). Reminders haalt al
  voorgestelde taken uit tekst in andere apps en categoriseert automatisch.

## Waar Tododeloo al sterk staat

Native (geen webwrapper) · gedeelde core · editorial design · **ochtendritueel**
(verwant aan Sunsama) · recurrence (RRULE) · Nederlandse NL quick-add · scheduling +
"Binnenkort"-overzicht · genotariseerde Mac-DMG · Siri quick-add.

---

## Ideeën — macOS (diepe integratie)

1. **✅ Native menubalk-commando's** _(gedaan, 2026-05-23)_
   File-menu (Nieuwe taak ⌘N, Nieuwe lijst ⌘⇧N), "Taak"-menu (af/onaf ⌘↵,
   hernoem ⌘R, verplaats ⌘D, verwijder ⌘⌫), "Ga"-menu (Vandaag/Alles/Binnenkort
   ⌘1–3, dag ± ⌘[ ⌘], ritueel opnieuw ⌘⇧R). Via `focusedSceneValue`.

2. **✅ MenuBarExtra (status-item)** _(gedaan, 2026-05-23)_
   Dropdown in de menubalk: vandaag's open todos (afvinken inline), één-regel
   quick-add, "Open Tododeloo" en "Ritueel opnieuw". `MenuBarExtra(.window)`-scene
   die de gedeelde `BoardModel` hergebruikt.

3. **✅ Globale quick-capture-hotkey** _(gedaan, 2026-05-23)_
   Systeembrede **⌃⌥⌘Space** opent een zwevend capture-paneel waar je ook bent
   (Akiflow); landt via `/quick-add` (Dutch parser). Carbon `RegisterEventHotKey`
   + een floating `NSPanel` (de Mac-app is non-sandboxed, dus geen extra permissie).

4. **Services-menu + Deel-extensie** — _impact: middel · effort: S–M · ⏳ nog te doen_
   "Voeg toe aan Tododeloo" op geselecteerde tekst in élke app → todo. Via App
   Intents/Services. Mac-tegenhanger van de iOS share-extensie.

5. **Dock-badge + native notificaties** — _impact: laag–middel · effort: S_
   Aantal open todos vandaag als dock-badge; herinneringen via `UNUserNotification`.

---

## Ideeën — iOS (diepe integratie)

1. **WidgetKit-widgets (Home · Lock Screen · StandBy)** — _impact: hoog · effort: M–L_
   "Vandaag"-lijst, open-count, en een **interactieve afvink-knop** (interactieve
   widgets krijgen ~3× engagement). Vereist een widget-extension-target + **App
   Group** om de Sanctum-token (Keychain) te delen. Data via een lichte
   cache/`/today`.

2. **Control Center-control + Action Button + Lock Screen** — _impact: hoog · effort: S–M_
   Eén control "Snel toevoegen" of "Open vandaag" (iOS 18/26). Hergebruikt de
   bestaande `QuickAddTodoIntent`.

3. **Live Activity / Dynamic Island** — _impact: middel · effort: M_
   Dag-voortgang live ("3/8 klaar") met een ring; tik = open vandaag. Mooi tijdens
   een werkdag, sluit aan op het ritueel-model.

4. **CoreSpotlight-indexering** — _impact: middel · effort: S–M_
   Todos doorzoekbaar in Spotlight, met deep link naar het detailscherm.

5. **Share-extensie** — _impact: middel · effort: M_
   Tekst/URL/afbeelding uit andere apps delen → todo (Reminders-achtig).

6. **✅ App Intents uitbreiden tot een echte intent-suite** _(gedaan, 2026-05-23)_
   `TodoEntity` (AppEntity) + `CompleteTodoIntent` ("markeer X af") +
   `TodaySummaryIntent` ("wat staat er vandaag"), bovenop de bestaande quick-add,
   met Siri-zinnen. Volgende stap: navigatie-intents ("open vandaag/Binnenkort")
   zodra er een gedeelde navigatie-state is.

7. **Focus-filters** — _impact: middel · effort: S–M_
   Koppel een lijst aan een Focus-modus (Werk → werklijst, Privé → master).

---

## Ideeën — cross-platform / backend

1. **Apple Intelligence — Foundation Models (on-device)** — _impact: hoog · effort: M · iOS/macOS 26_
   On-device LLM, geen server. Use-cases: een grote todo in subtaken splitsen
   (Todoist-AI), "plan mijn dag" als suggestie voor het ritueel, en een
   **wekelijkse review-samenvatting**. Privacyvriendelijk en gratis qua infra.

2. **Avond-/shutdown-ritueel** — _impact: hoog · effort: M_
   Spiegel van het ochtendritueel: review wat af is, vier het, en verplaats restjes
   in één gebaar naar morgen (Sunsama). Past naadloos op het bestaande ritueel +
   de net gebouwde reset/scheduling. Backend: nieuwe action `BuildEveningReview` +
   bulk-verplaatsing; clients hergebruiken de ritueel-UI-bouwstenen.

3. **Kalender-integratie + time-blocking (EventKit)** — _impact: hoog · effort: L_
   Toon de agenda naast "Vandaag"; sleep een todo naar een tijdsblok (Akiflow/
   Sunsama/Amie). Backend: optionele `starts_at`/`ends_at` op de `list_items`-pivot
   of de todo. Begin read-only (agenda tonen), dan twee-richtingen.

4. **Natural-language quick-add uitbreiden** — _impact: middel · effort: S–M_
   Tijden, `#lijst`, prioriteit-tokens (`!hoog`), tags (Todoist NLP). Bouwt voort
   op de bestaande Nederlandse datum/recurrence-parser; logica in de quick-add-action.

5. **Tijdgebonden herinneringen (push)** — _impact: middel · effort: M–L_
   Reminder-tijd op een todo → APNs/lokale notificatie. Backend: scheduled job.

6. **Notion-achtige optionele eigenschappen** — _impact: middel · effort: M · ⚠️ reductie_
   Optionele velden (tijdsinschatting, energie, context) voor rijkere filters/sort.
   Krachtig, maar bewaak streng dat de UI clean blijft — opt-in en verborgen tot
   gebruikt.

---

## Aanbevolen volgorde ("next three")

1. **macOS MenuBarExtra + globale quick-capture** — grootste dagelijkse winst op de
   Mac, bouwt direct op de net toegevoegde menu-commando's.
2. **iOS widgets + Control Center-control** — meeste zichtbaarheid op de telefoon;
   de App-Group-investering betaalt zich daarna terug voor share/intents.
3. **Gedeelde App-Intents-suite + Apple Intelligence subtaak-split** — maakt
   Tododeloo "OS-native" qua Siri/Shortcuts/Spotlight en voegt slimme,
   privacyvriendelijke hulp toe.

## Architectuur-noten & afhankelijkheden

- **App Group** is de sleutel-enabler: widgets, share-extensie en intents moeten de
  Sanctum-token uit de Keychain delen met de hoofd-app. Eén keer opzetten, daarna
  hergebruikbaar. Nieuwe targets toevoegen via de bestaande `xcodeproj`-Ruby-route
  (`add-mac-target.rb`-patroon) — niet handmatig in `project.pbxproj`.
- **Entitlements per feature:** EventKit (kalender), App Groups (extensies),
  Push (notificaties), Siri (al aanwezig).
- **Backend blijft leidend:** elke nieuwe mogelijkheid eerst als action + dun
  endpoint; clients zijn views. Nieuwe endpoints vereisen een **Forge-deploy** voor
  ze op fysieke devices werken (de apps wijzen naar productie).
- **OS-versies:** Foundation Models, nieuwste controls/Live-Activity-gedrag vragen
  iOS/macOS 26; widgets/Spotlight/App-Intents werken ruim daaronder.

## Bronnen

- [Zapier — 6 best to-do list apps for Mac (2026)](https://zapier.com/blog/best-mac-to-do-list-apps/)
- [Zapier — 7 best to-do list apps of 2026](https://zapier.com/blog/best-todo-list-apps/)
- [Akiflow — Ultimate guide to time-blocking planner apps](https://akiflow.com/blog/best-time-blocking-planner-apps)
- [Efficient.app — Sunsama vs Amie (2026)](https://efficient.app/compare/sunsama-vs-amie)
- [Apple Developer — Adding interactivity to widgets and Live Activities](https://developer.apple.com/documentation/widgetkit/adding-interactivity-to-widgets-and-live-activities)
- [9to5Mac — Apple Intelligence new features in iOS 26](https://9to5mac.com/2025/10/15/apple-intelligence-new-features-in-ios-26-full-list/)
- [Apple — Foundation Models framework](https://www.apple.com/newsroom/2025/09/apples-foundation-models-framework-unlocks-new-intelligent-app-experiences/)
- [Apple Support — Suggested reminders from any app in iOS 26](https://support.apple.com/en-us/124025)
- [Notion 3.2 release notes (Jan 2026)](https://www.notion.com/releases/2026-01-20)

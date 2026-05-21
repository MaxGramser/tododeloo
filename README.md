# Tododeloo

Een todo-app voor mensen die gek worden van todos die uitgesmeerd zitten over losse lijstjes.

## Waarom ik dit heb gebouwd

Mijn probleem: ik had één _totaal_-lijst nodig, maar tegelijk losse lijstjes per dag, per project, en voor dingen tussendoor. Het resultaat was elke keer een gefragmenteerde wirwar — een todo op vrijdag's lijst, dezelfde todo half-ingevuld in mijn boodschappen-app, en nooit een goed overzicht van wat er echt op mijn bordje lag.

Apple Reminders heeft de UX-kwaliteit die ik wil (inline toevoegen, drag-and-drop, alles snel en zonder wrijving), maar mist het concept: een **master**-lijst als single source of truth, terwijl losse daglijsten en project-lijsten naar diezelfde todos _verwijzen_ in plaats van kopieën te maken. Dus heb ik dat zelf gebouwd.

## Wat het anders maakt

**Eén todo, meerdere lijsten.** Een todo bestaat één keer in de database. Lijsten zijn verwijzingen, geen kopieën. Vink een todo af in je daglijst en hij staat ook in master meteen op done. Voeg iets toe aan je daglijst en het verschijnt automatisch in master. Geen sync-issues, geen dubbelingen.

**Ochtendritueel in plaats van een lege lijst.** Open de app op een werkdag en je krijgt drie vragen: welke niet-afgemaakte todos van gisteren wil je meenemen, wat pak je uit master, en wat is nieuw vandaag. Geen passieve todo-bak — een actieve dag-planning.

**Quick-add die slim is over werkdagen.** `⌘K` opent een snelle input. Op een werkdag landt het op vandaag, in het weekend op maandag — want zaterdag is geen werkdag.

**Apple Reminders-niveau interactie.** Inline toevoegen aan de onderkant van elke lijst (focus blijft staan voor de volgende). Drag-and-drop voor handmatige sortering. Done-items zakken automatisch naar onder, doorgestreept. Sort modes per lijst. Rechtermuisknop opent een rijk context-menu met submenus voor prioriteit, tags, datum verplaatsen, en lijst-koppeling.

**Editoriële look, geen SaaS-dashboard.** Zware display-typografie + monospace labels + één sterk oranje accent + off-white achtergrond. Voelt eerder als een tijdschrift dan een productiviteit-tool.

## Wat het kan

- Master lijst (alles), daglijsten per werkdag, custom lijsten per project of thema
- Same-todo-multiple-lists via pivot-relatie — staat geheid in sync
- Carry-over ochtendritueel met opties uit vorige werkdag, master en nieuw
- Globale quick-add: persistent bar in de header, `⌘K` / `N` modal, of FAB op mobiel — toont expliciet waar het naartoe gaat (`→ Vandaag` / `→ Maandag`)
- Inline toevoegen onderaan elke lijst; type, enter, herhaal
- Sort modes per lijst (handmatig, chronologisch, alfabetisch, prioriteit)
- Drag-and-drop in manual mode (vue-draggable-plus)
- Done-todos sorteren automatisch onderaan
- Soft-delete met undo-toast
- Tags + prioriteit (low/normal/high), met filter-balk per lijst
- Rechtermuisknop context-menu per todo:
  - Markeer done/open
  - Bewerk titel
  - Prioriteit (submenu)
  - Tags toevoegen/weghalen (submenu, checkboxes)
  - Verplaats naar dag (vandaag / morgen / volgende werkdag / specifieke datum)
  - Zet op andere lijst (submenu met al je custom lijsten)
  - Zet in vandaag of verwijder van vandaag (blijft wel in master)
  - Dupliceer
  - Volledig verwijderen
- Multi-user vanaf de start (Laravel Fortify, passkeys + 2FA ingebakken)
- Dev quick-login knop op de loginpagina (alleen buiten productie) voor snelle iteratie

## Tech & filosofie

Laravel 13 + Inertia v3 + Vue 3 + Tailwind 4. PHP 8.4. SQLite voor dev.

**Action classes, dunne controllers.** Elke mutatie zit in een single-purpose action class onder `app/Actions/`. Controllers valideren input en roepen de action aan, that's it. De reden: er komt later een iOS-app die diezelfde Laravel backend gebruikt via een JSON API. Geen dubbele business logic.

**Pest tests overal.** 90+ feature tests dekken model-relaties, soft-delete sync, workday-kalender, alle acties, en HTTP routes inclusief autorisatie.

## Roadmap

Kort:
- Repeating todos
- Due dates (los van daglijst-koppeling)
- Snooze / postpone shortcut
- Markdown-rendering in description
- Keyboard navigation door de lijst

Lang:
- iOS-app (native, eet dezelfde backend via JSON API)
- Gedeelde lijsten
- Calendar integratie

## Lokaal draaien

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
composer run dev
```

De app draait via Laravel Herd op `https://tododeloo.test`. Op de login-pagina zie je in local env een **Fast login (dev)** knop voor snelle iteratie — maakt zo nodig een dev-account aan en logt je meteen in.

## English summary

Tododeloo is a personal todo app for people who get tired of todos scattered across separate lists. One master list as the single source of truth; daily and custom lists are references, not copies. Mark something done in your daily list and it's done in master too — no sync, no duplicates.

Workday-aware quick-add (weekend additions land on Monday), Apple Reminders-quality inline interactions, an opinionated morning ritual that asks what you're carrying over from yesterday, and an editorial Swiss-design aesthetic instead of the usual SaaS dashboard. Built with Laravel 13 + Inertia + Vue 3 with an action-class architecture so an iOS companion can share the same backend later.

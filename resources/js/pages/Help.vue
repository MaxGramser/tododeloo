<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PageHero from '@/components/PageHero.vue';
import Kbd from '@/components/Kbd.vue';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Handleiding', href: '/help' }],
    },
});

const toc = [
    { id: 'idee', label: 'Het idee' },
    { id: 'taken', label: 'Taken: de basis' },
    { id: 'snel', label: 'Snel toevoegen' },
    { id: 'lijsten', label: 'Lijsten' },
    { id: 'ritueel', label: 'Het ochtendritueel' },
    { id: 'plannen', label: 'Plannen & Binnenkort' },
    { id: 'herhalen', label: 'Herhalende taken' },
    { id: 'subtaken', label: 'Subtaken' },
    { id: 'tags', label: 'Tags, filters & sorteren' },
    { id: 'ios', label: 'De iOS-app' },
    { id: 'siri', label: 'Siri & Snelkoppelingen' },
    { id: 'mac', label: 'De Mac-app & sneltoetsen' },
    { id: 'capture', label: 'Globale quick-capture' },
    { id: 'account', label: 'Account & sync' },
];

const macGroups = [
    {
        menu: 'Archief',
        rows: [
            { action: 'Nieuwe taak (focust de snelinvoer)', keys: '⌘N' },
            { action: 'Nieuwe lijst', keys: '⇧⌘N' },
        ],
    },
    {
        menu: 'Taak (werkt op de geselecteerde taak)',
        rows: [
            { action: 'Markeer af / onaf', keys: '⌘↩' },
            { action: 'Hernoem', keys: '⌘R' },
            { action: 'Verplaats naar datum…', keys: '⌘D' },
            { action: 'Verwijder', keys: '⌘⌫' },
        ],
    },
    {
        menu: 'Ga',
        rows: [
            { action: 'Vandaag', keys: '⌘1' },
            { action: 'Alles (master)', keys: '⌘2' },
            { action: 'Binnenkort', keys: '⌘3' },
            { action: 'Vorige dag', keys: '⌘[' },
            { action: 'Volgende dag', keys: '⌘]' },
            { action: 'Ritueel opnieuw', keys: '⇧⌘R' },
        ],
    },
];

const siriPhrases = [
    { phrase: 'Voeg toe aan Tododeloo', note: 'Siri vraagt daarna wát je wilt toevoegen.' },
    { phrase: 'Markeer een taak af in Tododeloo', note: 'Kies een van je open taken van vandaag.' },
    { phrase: 'Wat staat er vandaag in Tododeloo', note: 'Siri leest je open taken voor.' },
];
</script>

<template>
    <Head title="Handleiding" />

    <PageHero
        eyebrow="handleiding"
        title="Alles over Tododeloo"
        accent
        sub="Eén plek die werkelijk elke functie uitlegt — van de basis tot de sneltoetsen, Siri en de globale quick-capture."
    />

    <div class="px-6 pb-32 sm:px-10">
        <!-- Inhoudsopgave -->
        <nav class="mt-2 rounded-xl border border-border bg-card p-5">
            <p class="font-mono text-[10px] tracking-widest text-muted-foreground uppercase">
                inhoud
            </p>
            <ul class="mt-3 grid gap-x-6 gap-y-2 sm:grid-cols-2 lg:grid-cols-3">
                <li v-for="(item, i) in toc" :key="item.id">
                    <a
                        :href="`#${item.id}`"
                        class="group flex items-baseline gap-2 text-sm text-muted-foreground transition-colors hover:text-foreground"
                    >
                        <span class="font-mono text-[10px] text-accent"
                            >{{ String(i + 1).padStart(2, '0') }}</span
                        >
                        <span class="underline-offset-4 group-hover:underline">{{ item.label }}</span>
                    </a>
                </li>
            </ul>
        </nav>

        <div class="mt-6 space-y-14">
            <!-- 1. Het idee -->
            <section id="idee" class="scroll-mt-24">
                <p class="font-mono text-[10px] tracking-widest text-accent uppercase">01 — het idee</p>
                <h2 class="mt-1 text-2xl font-bold tracking-tight">Eén taak, overal zichtbaar</h2>
                <div class="mt-4 max-w-2xl space-y-4 text-sm leading-relaxed text-muted-foreground">
                    <p>
                        Tododeloo draait om één gedachte: een <strong class="font-medium text-foreground">taak</strong>
                        bestaat één keer, en lijsten zijn er slechts <em>verwijzingen</em> naar. Dezelfde taak kan
                        tegelijk in <strong class="font-medium text-foreground">Master</strong> (je volledige voorraad),
                        op een <strong class="font-medium text-foreground">dag</strong>, en in een
                        <strong class="font-medium text-foreground">eigen lijst</strong> staan — zonder kopieën.
                    </p>
                    <p>
                        Plannen doe je niet met een datumveld, maar door een taak op de
                        <strong class="font-medium text-foreground">daglijst</strong> van die dag te zetten. Zo blijft
                        alles één samenhangend geheel, en zie je op elk apparaat (web, iPhone, Mac) precies hetzelfde.
                    </p>
                </div>
            </section>

            <!-- 2. Taken -->
            <section id="taken" class="scroll-mt-24">
                <p class="font-mono text-[10px] tracking-widest text-accent uppercase">02 — de basis</p>
                <h2 class="mt-1 text-2xl font-bold tracking-tight">Taken</h2>
                <div class="mt-4 max-w-2xl space-y-3 text-sm leading-relaxed text-muted-foreground">
                    <ul class="space-y-2">
                        <li class="flex gap-2.5">
                            <span class="mt-2 size-1 shrink-0 rounded-full bg-accent" />
                            <span><strong class="font-medium text-foreground">Afvinken</strong> — klik op het rondje. Heeft een taak subtaken, dan vink je de subtaken af; de taak wordt automatisch af zodra alles klaar is.</span>
                        </li>
                        <li class="flex gap-2.5">
                            <span class="mt-2 size-1 shrink-0 rounded-full bg-accent" />
                            <span><strong class="font-medium text-foreground">Openen</strong> — klik op de titel voor het detailvenster (beschrijving, subtaken, tags, herhaling).</span>
                        </li>
                        <li class="flex gap-2.5">
                            <span class="mt-2 size-1 shrink-0 rounded-full bg-accent" />
                            <span><strong class="font-medium text-foreground">Prioriteit</strong> — laag, normaal of hoog. Hoog krijgt een oranje accent.</span>
                        </li>
                        <li class="flex gap-2.5">
                            <span class="mt-2 size-1 shrink-0 rounded-full bg-accent" />
                            <span><strong class="font-medium text-foreground">Acties</strong> — via het contextmenu (rechtsklik op web/Mac, ingedrukt houden op iOS): hernoemen, verplaatsen, dupliceren, op een lijst zetten, uit een lijst halen, verwijderen.</span>
                        </li>
                        <li class="flex gap-2.5">
                            <span class="mt-2 size-1 shrink-0 rounded-full bg-accent" />
                            <span><strong class="font-medium text-foreground">Verwijderen kan ongedaan</strong> — er verschijnt kort een "ongedaan maken".</span>
                        </li>
                    </ul>
                </div>
            </section>

            <!-- 3. Snel toevoegen -->
            <section id="snel" class="scroll-mt-24">
                <p class="font-mono text-[10px] tracking-widest text-accent uppercase">03 — snelheid</p>
                <h2 class="mt-1 text-2xl font-bold tracking-tight">Snel toevoegen & natuurlijke taal</h2>
                <div class="mt-4 max-w-2xl space-y-4 text-sm leading-relaxed text-muted-foreground">
                    <p>
                        Het snelinvoerveld bovenaan begrijpt <strong class="font-medium text-foreground">Nederlandse taal</strong>.
                        Typ gewoon je taak met een tijdsaanduiding erin en Tododeloo plant 'm meteen:
                    </p>
                    <ul class="space-y-2">
                        <li class="flex gap-2.5"><span class="mt-2 size-1 shrink-0 rounded-full bg-accent" /><span><span class="text-foreground">"Bel de tandarts <em>morgen</em>"</span> → ingepland voor morgen.</span></li>
                        <li class="flex gap-2.5"><span class="mt-2 size-1 shrink-0 rounded-full bg-accent" /><span><span class="text-foreground">"Belasting <em>volgende week dinsdag</em>"</span> → die datum.</span></li>
                        <li class="flex gap-2.5"><span class="mt-2 size-1 shrink-0 rounded-full bg-accent" /><span><span class="text-foreground">"Sporten <em>elke werkdag</em>"</span> → maakt er een herhalende taak van.</span></li>
                    </ul>
                    <p>
                        Zonder datum landt een taak op de lijst waar je bent (vandaag / master / de open lijst). Op een
                        <strong class="font-medium text-foreground">weekend</strong> schuift "vandaag" automatisch naar de
                        eerstvolgende maandag. Een korte bevestiging laat zien wat er gebeurde.
                    </p>
                </div>
            </section>

            <!-- 4. Lijsten -->
            <section id="lijsten" class="scroll-mt-24">
                <p class="font-mono text-[10px] tracking-widest text-accent uppercase">04 — structuur</p>
                <h2 class="mt-1 text-2xl font-bold tracking-tight">Lijsten: Master, dagen, eigen</h2>
                <div class="mt-4 max-w-2xl space-y-3 text-sm leading-relaxed text-muted-foreground">
                    <ul class="space-y-2">
                        <li class="flex gap-2.5"><span class="mt-2 size-1 shrink-0 rounded-full bg-accent" /><span><strong class="font-medium text-foreground">Master ("Alles")</strong> — je volledige voorraad. Elke taak staat hier altijd.</span></li>
                        <li class="flex gap-2.5"><span class="mt-2 size-1 shrink-0 rounded-full bg-accent" /><span><strong class="font-medium text-foreground">Daglijsten</strong> — één per datum. Een taak op een daglijst is "gepland voor die dag".</span></li>
                        <li class="flex gap-2.5"><span class="mt-2 size-1 shrink-0 rounded-full bg-accent" /><span><strong class="font-medium text-foreground">Eigen lijsten</strong> — voor projecten of thema's. Aanmaken via de <span class="text-foreground">+</span> naast "lijsten" in de zijbalk.</span></li>
                    </ul>
                    <p>
                        Omdat een taak gedeeld is, betekent "op een lijst zetten" een extra verwijzing toevoegen, en "uit
                        een lijst halen" enkel die verwijzing weghalen — de taak zelf blijft bestaan. Elke lijst heeft een
                        <strong class="font-medium text-foreground">sorteermodus</strong> (handmatig, nieuwste, alfabetisch,
                        prioriteit) en je kunt in handmatige modus <strong class="font-medium text-foreground">slepen</strong>
                        om te herordenen. Afgeronde taken zakken automatisch naar onderen.
                    </p>
                </div>
            </section>

            <!-- 5. Ritueel -->
            <section id="ritueel" class="scroll-mt-24">
                <p class="font-mono text-[10px] tracking-widest text-accent uppercase">05 — focus</p>
                <h2 class="mt-1 text-2xl font-bold tracking-tight">Het ochtendritueel</h2>
                <div class="mt-4 max-w-2xl space-y-4 text-sm leading-relaxed text-muted-foreground">
                    <p>
                        Begin je dag bewust. De eerste keer dat je "Vandaag" opent, verschijnt het ritueel: je kiest wat je
                        écht vandaag gaat doen. De blokken:
                    </p>
                    <ul class="space-y-2">
                        <li class="flex gap-2.5"><span class="mt-2 size-1 shrink-0 rounded-full bg-accent" /><span><strong class="font-medium text-foreground">Vorige werkdag</strong> — wat bleef liggen (carry-over).</span></li>
                        <li class="flex gap-2.5"><span class="mt-2 size-1 shrink-0 rounded-full bg-accent" /><span><strong class="font-medium text-foreground">Eerder</strong> — langer blijven liggen.</span></li>
                        <li class="flex gap-2.5"><span class="mt-2 size-1 shrink-0 rounded-full bg-accent" /><span><strong class="font-medium text-foreground">Master</strong> — alles wat nog open staat.</span></li>
                        <li class="flex gap-2.5"><span class="mt-2 size-1 shrink-0 rounded-full bg-accent" /><span><strong class="font-medium text-foreground">Al gepland</strong> — wat al voor vandaag op de planning stond.</span></li>
                    </ul>
                    <p>
                        Je vinkt aan wat meegaat, typt eventueel nieuwe taken, en drukt op
                        <strong class="font-medium text-foreground">"Start de dag"</strong>.
                    </p>
                    <div class="rounded-lg border border-border bg-card p-4">
                        <p class="font-mono text-[10px] tracking-widest text-accent uppercase">ritueel opnieuw</p>
                        <p class="mt-1.5">
                            Per ongeluk te snel gestart? Gebruik <strong class="font-medium text-foreground">"Ritueel opnieuw"</strong>
                            (web: in de paginakop; iPhone: in het <span class="text-foreground">…</span>-menu; Mac: menu <em>Ga</em> of <Kbd keys="⇧⌘R" />).
                            Dit opent het ritueel-scherm gewoon weer — <strong class="font-medium text-foreground">je taken blijven staan</strong>
                            (ze verschijnen onder "al gepland"). Het is niet-destructief.
                        </p>
                    </div>
                </div>
            </section>

            <!-- 6. Plannen & Binnenkort -->
            <section id="plannen" class="scroll-mt-24">
                <p class="font-mono text-[10px] tracking-widest text-accent uppercase">06 — vooruitkijken</p>
                <h2 class="mt-1 text-2xl font-bold tracking-tight">Plannen, datums & Binnenkort</h2>
                <div class="mt-4 max-w-2xl space-y-4 text-sm leading-relaxed text-muted-foreground">
                    <ul class="space-y-2">
                        <li class="flex gap-2.5"><span class="mt-2 size-1 shrink-0 rounded-full bg-accent" /><span><strong class="font-medium text-foreground">Verplaats naar (datum)</strong> — via het contextmenu van een taak. Kies een dag en de taak verschijnt daar.</span></li>
                        <li class="flex gap-2.5"><span class="mt-2 size-1 shrink-0 rounded-full bg-accent" /><span><strong class="font-medium text-foreground">Binnenkort</strong> — een overzicht van de komende dagen mét hun taken, zodat je ziet wanneer iets relevant wordt. (Web: "upcoming" in de zijbalk · iPhone: de Binnenkort-tab · Mac: de Binnenkort-sectie in de zijbalk.)</span></li>
                        <li class="flex gap-2.5"><span class="mt-2 size-1 shrink-0 rounded-full bg-accent" /><span><strong class="font-medium text-foreground">Datum-chip</strong> — buiten de dagweergave toont een taak een kalenderlabel ("morgen", "wo 27 mei") zodat je in één oogopslag ziet wanneer 'm relevant wordt.</span></li>
                    </ul>
                </div>
            </section>

            <!-- 7. Herhalen -->
            <section id="herhalen" class="scroll-mt-24">
                <p class="font-mono text-[10px] tracking-widest text-accent uppercase">07 — automatisch</p>
                <h2 class="mt-1 text-2xl font-bold tracking-tight">Herhalende taken</h2>
                <div class="mt-4 max-w-2xl space-y-4 text-sm leading-relaxed text-muted-foreground">
                    <p>
                        Maak van een taak een herhaling via het contextmenu → <strong class="font-medium text-foreground">Herhaal</strong>.
                        Kies een preset (elke dag, elke werkdag, wekelijks, maandelijks op de zoveelste, half jaar, jaar) of
                        bouw een <strong class="font-medium text-foreground">aangepaste regel</strong> (elke N dagen/weken/maanden,
                        specifieke weekdagen).
                    </p>
                    <p>
                        Elke keer dat de regel "vuurt", verschijnt er automatisch een nieuwe instantie op die dag. Een
                        herhalende taak wordt nooit als carry-over aangeboden (die regenereert immers). Stoppen kan altijd
                        via <strong class="font-medium text-foreground">"Stop herhaling"</strong>.
                    </p>
                </div>
            </section>

            <!-- 8. Subtaken -->
            <section id="subtaken" class="scroll-mt-24">
                <p class="font-mono text-[10px] tracking-widest text-accent uppercase">08 — opdelen</p>
                <h2 class="mt-1 text-2xl font-bold tracking-tight">Subtaken</h2>
                <div class="mt-4 max-w-2xl space-y-4 text-sm leading-relaxed text-muted-foreground">
                    <p>
                        Voeg subtaken toe via het contextmenu of het detailvenster. De hoofd-taak toont dan een
                        <strong class="font-medium text-foreground">voortgangsring</strong> in plaats van een rondje, en wordt
                        pas automatisch afgevinkt als álle subtaken klaar zijn. Klik op de ring om de taak te openen en de
                        subtaken af te ronden.
                    </p>
                </div>
            </section>

            <!-- 9. Tags & filters -->
            <section id="tags" class="scroll-mt-24">
                <p class="font-mono text-[10px] tracking-widest text-accent uppercase">09 — overzicht</p>
                <h2 class="mt-1 text-2xl font-bold tracking-tight">Tags, filters & sorteren</h2>
                <div class="mt-4 max-w-2xl space-y-3 text-sm leading-relaxed text-muted-foreground">
                    <ul class="space-y-2">
                        <li class="flex gap-2.5"><span class="mt-2 size-1 shrink-0 rounded-full bg-accent" /><span><strong class="font-medium text-foreground">Tags</strong> — geef taken een kleurlabel en filter erop.</span></li>
                        <li class="flex gap-2.5"><span class="mt-2 size-1 shrink-0 rounded-full bg-accent" /><span><strong class="font-medium text-foreground">Filteren</strong> — op prioriteit of tag, via het filtermenu van een lijst.</span></li>
                        <li class="flex gap-2.5"><span class="mt-2 size-1 shrink-0 rounded-full bg-accent" /><span><strong class="font-medium text-foreground">Sorteren</strong> — handmatig (slepen), nieuwste eerst, alfabetisch of op prioriteit.</span></li>
                    </ul>
                </div>
            </section>

            <!-- 10. iOS -->
            <section id="ios" class="scroll-mt-24">
                <p class="font-mono text-[10px] tracking-widest text-accent uppercase">10 — onderweg</p>
                <h2 class="mt-1 text-2xl font-bold tracking-tight">De iOS-app</h2>
                <div class="mt-4 max-w-2xl space-y-3 text-sm leading-relaxed text-muted-foreground">
                    <ul class="space-y-2">
                        <li class="flex gap-2.5"><span class="mt-2 size-1 shrink-0 rounded-full bg-accent" /><span>Drie tabs: <strong class="font-medium text-foreground">Vandaag</strong>, <strong class="font-medium text-foreground">Binnenkort</strong> en <strong class="font-medium text-foreground">Lijsten</strong>.</span></li>
                        <li class="flex gap-2.5"><span class="mt-2 size-1 shrink-0 rounded-full bg-accent" /><span>Houd een taak <strong class="font-medium text-foreground">ingedrukt</strong> voor het volledige actiemenu (afvinken, prioriteit, verplaatsen, herhalen, subtaak, dupliceren, verwijderen).</span></li>
                        <li class="flex gap-2.5"><span class="mt-2 size-1 shrink-0 rounded-full bg-accent" /><span>In <strong class="font-medium text-foreground">Binnenkort</strong> tik je een dag aan om naar dat volledige dag-board te gaan.</span></li>
                        <li class="flex gap-2.5"><span class="mt-2 size-1 shrink-0 rounded-full bg-accent" /><span>Inloggen ondersteunt <strong class="font-medium text-foreground">wachtwoord-autofill</strong> via je iCloud-sleutelhanger.</span></li>
                    </ul>
                </div>
            </section>

            <!-- 11. Siri -->
            <section id="siri" class="scroll-mt-24">
                <p class="font-mono text-[10px] tracking-widest text-accent uppercase">11 — handsfree</p>
                <h2 class="mt-1 text-2xl font-bold tracking-tight">Siri & Snelkoppelingen</h2>
                <div class="mt-4 max-w-2xl space-y-4 text-sm leading-relaxed text-muted-foreground">
                    <p>Tododeloo kent een paar Siri-zinnen en App Intents — bruikbaar in Siri, de Snelkoppelingen-app en Spotlight:</p>
                    <div class="space-y-2">
                        <div
                            v-for="s in siriPhrases"
                            :key="s.phrase"
                            class="rounded-lg border border-border bg-card p-3"
                        >
                            <p class="text-foreground">“{{ s.phrase }}”</p>
                            <p class="mt-0.5 text-xs">{{ s.note }}</p>
                        </div>
                    </div>
                    <p>
                        Wil je in één adem "<em>voeg melk toe aan Tododeloo</em>" zeggen? iOS staat geen vrije tekst in de
                        triggerzin zelf toe. Maak daarvoor in de <strong class="font-medium text-foreground">Snelkoppelingen-app</strong>
                        een shortcut met een stap <strong class="font-medium text-foreground">"Dicteer tekst"</strong> die de
                        "Voeg toe"-intent voedt.
                    </p>
                </div>
            </section>

            <!-- 12. Mac & shortcuts -->
            <section id="mac" class="scroll-mt-24">
                <p class="font-mono text-[10px] tracking-widest text-accent uppercase">12 — desktop</p>
                <h2 class="mt-1 text-2xl font-bold tracking-tight">De Mac-app & sneltoetsen</h2>
                <div class="mt-4 max-w-2xl space-y-4 text-sm leading-relaxed text-muted-foreground">
                    <p>
                        Een drie-koloms venster: zijbalk · takenlijst · inspector. <strong class="font-medium text-foreground">Dubbelklik</strong>
                        een titel om inline te hernoemen (klik weg of Enter bewaart). <strong class="font-medium text-foreground">Rechtsklik</strong>
                        voor het actiemenu. En een volledige <strong class="font-medium text-foreground">menubalk</strong> met sneltoetsen:
                    </p>
                </div>

                <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="group in macGroups"
                        :key="group.menu"
                        class="rounded-xl border border-border bg-card p-4"
                    >
                        <p class="font-mono text-[10px] tracking-widest text-muted-foreground uppercase">
                            {{ group.menu }}
                        </p>
                        <dl class="mt-3 space-y-2">
                            <div
                                v-for="row in group.rows"
                                :key="row.action"
                                class="flex items-center justify-between gap-3 text-sm"
                            >
                                <dt class="text-muted-foreground">{{ row.action }}</dt>
                                <dd><Kbd :keys="row.keys" /></dd>
                            </div>
                        </dl>
                    </div>
                </div>

                <div class="mt-5 max-w-2xl rounded-lg border border-border bg-card p-4 text-sm leading-relaxed text-muted-foreground">
                    <p class="font-mono text-[10px] tracking-widest text-accent uppercase">menubalk-icoon</p>
                    <p class="mt-1.5">
                        Naast het venster zit Tododeloo ook in de <strong class="font-medium text-foreground">menubalk</strong>
                        (het ✓-icoon): je open taken van vandaag, een snelinvoer, inline afvinken, en "Open Tododeloo" /
                        "Ritueel opnieuw" — zonder het hoofdvenster te openen.
                    </p>
                </div>
            </section>

            <!-- 13. Global capture -->
            <section id="capture" class="scroll-mt-24">
                <p class="font-mono text-[10px] tracking-widest text-accent uppercase">13 — overal</p>
                <h2 class="mt-1 text-2xl font-bold tracking-tight">Globale quick-capture</h2>
                <div class="mt-4 max-w-2xl space-y-4 text-sm leading-relaxed text-muted-foreground">
                    <p>
                        Op de Mac kun je <strong class="font-medium text-foreground">vanuit elke app</strong> een taak
                        wegschrijven, zonder te wisselen van venster. Druk op:
                    </p>
                    <p class="flex items-center gap-2">
                        <Kbd keys="⌃" /><span>+</span><Kbd keys="⌥" /><span>+</span><Kbd keys="⌘" /><span>+</span><Kbd keys="Spatie" />
                    </p>
                    <p>
                        Er verschijnt een zwevend invoerveldje. Typ je taak (natuurlijke taal werkt: "<em>bel Jan morgen</em>"),
                        druk op Enter, en het landt via dezelfde snel-toevoeg-route. <Kbd keys="Esc" /> sluit zonder iets te doen.
                    </p>
                </div>
            </section>

            <!-- 14. Account & sync -->
            <section id="account" class="scroll-mt-24">
                <p class="font-mono text-[10px] tracking-widest text-accent uppercase">14 — alles bij elkaar</p>
                <h2 class="mt-1 text-2xl font-bold tracking-tight">Account & synchronisatie</h2>
                <div class="mt-4 max-w-2xl space-y-4 text-sm leading-relaxed text-muted-foreground">
                    <p>
                        Web, iPhone en Mac praten met hetzelfde account, dus je data is overal identiek. De native apps
                        loggen één keer in; daarna lopen alle acties via je account. De server-URL is in de iOS- en Mac-app
                        instelbaar (handig voor een eigen omgeving).
                    </p>
                    <p>
                        Mis je iets, of klopt er iets niet? Deze handleiding groeit mee — kom gerust terug.
                    </p>
                </div>
            </section>
        </div>
    </div>
</template>

<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    CalendarDays,
    Inbox,
    Keyboard,
    MousePointer2,
    Sun,
} from 'lucide-vue-next';
import { dashboard, login, register } from '@/routes';

defineProps<{
    auth?: { user?: unknown };
}>();
</script>

<template>
    <Head title="Tododeloo — focus op vandaag, zonder lijstjes-chaos" />

    <div class="min-h-screen bg-background text-foreground antialiased">
        <!-- nav -->
        <header
            class="sticky top-0 z-40 border-b border-border/60 bg-background/80 backdrop-blur"
        >
            <div
                class="mx-auto flex max-w-6xl items-center justify-between px-6 py-4 sm:px-8"
            >
                <div class="flex items-center gap-3">
                    <span
                        class="hatched inline-block size-7 rounded-md bg-accent"
                    />
                    <span
                        class="brand-dot text-lg leading-none font-bold tracking-tight"
                        >Tododeloo</span
                    >
                </div>
                <nav class="flex items-center gap-2 text-sm">
                    <Link
                        v-if="$page.props.auth?.user"
                        :href="dashboard()"
                        class="inline-flex items-center gap-1 rounded-md px-3 py-1.5 font-medium hover:bg-secondary"
                    >
                        Dashboard
                        <ArrowRight class="size-3.5" />
                    </Link>
                    <template v-else>
                        <Link
                            :href="login()"
                            class="rounded-md px-3 py-1.5 hover:bg-secondary"
                        >
                            Inloggen
                        </Link>
                        <Link
                            :href="register()"
                            class="inline-flex items-center gap-1 rounded-md bg-accent px-3 py-1.5 font-medium text-accent-foreground hover:bg-accent/90"
                        >
                            Begin
                            <ArrowRight class="size-3.5" />
                        </Link>
                    </template>
                </nav>
            </div>
        </header>

        <!-- hero -->
        <section class="border-b border-border/60">
            <div class="mx-auto max-w-6xl px-6 py-20 sm:px-8 sm:py-28">
                <div
                    class="grid grid-cols-1 items-end gap-12 lg:grid-cols-[1.4fr_1fr]"
                >
                    <div class="flex flex-col gap-6">
                        <span
                            class="font-mono text-[11px] tracking-[0.3em] text-muted-foreground uppercase"
                        >
                            focus · today · master
                        </span>
                        <h1
                            class="text-5xl leading-[0.95] font-bold tracking-tight sm:text-7xl"
                        >
                            <span class="brand-dot">Eén todo</span><br />
                            <span class="text-muted-foreground/70">op vele plekken,</span
                            ><br />
                            altijd in sync.
                        </h1>
                        <p
                            class="max-w-prose text-lg leading-relaxed text-muted-foreground"
                        >
                            Tododeloo is een todo-app voor mensen die gek
                            worden van todos uitgesmeerd over losse lijstjes.
                            Eén master als bron van waarheid, daglijsten en
                            project-lijsten als verwijzingen — geen kopieën,
                            geen sync-issues.
                        </p>
                        <div class="flex flex-wrap items-center gap-3 pt-2">
                            <Link
                                :href="register()"
                                class="inline-flex items-center gap-2 rounded-lg bg-accent px-5 py-2.5 font-medium text-accent-foreground transition-colors hover:bg-accent/90"
                            >
                                Maak een account
                                <ArrowRight class="size-4" />
                            </Link>
                            <Link
                                :href="login()"
                                class="inline-flex items-center gap-2 rounded-lg border border-input px-5 py-2.5 font-medium hover:bg-secondary"
                            >
                                Heb al een account
                            </Link>
                        </div>
                    </div>

                    <!-- editorial hero block -->
                    <div
                        class="relative aspect-[4/5] overflow-hidden rounded-2xl border border-border bg-card shadow-sm"
                    >
                        <div
                            class="absolute top-0 left-0 h-1/3 w-full border-b border-border/60 bg-background"
                        >
                            <div
                                class="flex h-full items-center justify-between px-5"
                            >
                                <span
                                    class="brand-dot text-2xl leading-none font-bold tracking-tight"
                                    >Vandaag</span
                                >
                                <span
                                    class="font-mono text-[10px] tracking-[0.25em] text-muted-foreground uppercase"
                                    >don 22 mei</span
                                >
                            </div>
                        </div>
                        <div class="absolute top-1/3 left-0 h-2/3 w-1/2">
                            <div
                                class="hatched flex h-full flex-col items-start justify-between bg-accent p-5 text-accent-foreground"
                            >
                                <span
                                    class="font-mono text-[10px] tracking-[0.25em] uppercase"
                                    >klaar</span
                                >
                                <div class="flex flex-col items-start gap-1">
                                    <span
                                        class="text-6xl leading-none font-bold"
                                        >72%</span
                                    >
                                    <span
                                        class="font-mono text-[10px] tracking-[0.25em] uppercase"
                                        >5 van 7</span
                                    >
                                </div>
                            </div>
                        </div>
                        <div
                            class="absolute top-1/3 right-0 flex h-2/3 w-1/2 flex-col gap-3 p-5"
                        >
                            <div class="flex items-center gap-2 text-sm">
                                <span
                                    class="grid size-4 place-items-center rounded-full bg-accent text-accent-foreground"
                                >
                                    <svg
                                        class="size-2.5"
                                        viewBox="0 0 16 16"
                                        fill="currentColor"
                                    >
                                        <path
                                            d="M12.416 3.376a.75.75 0 0 1 .208 1.04l-5 7.5a.75.75 0 0 1-1.131.094l-3-3a.75.75 0 1 1 1.06-1.06l2.37 2.37 4.453-6.678a.75.75 0 0 1 1.04-.266Z"
                                        />
                                    </svg>
                                </span>
                                <span
                                    class="text-foreground/60 line-through decoration-foreground/40"
                                >
                                    Site review schrijven
                                </span>
                            </div>
                            <div class="flex items-center gap-2 text-sm">
                                <span
                                    class="size-4 rounded-full border border-input"
                                />
                                <span class="font-medium">Klant bellen</span>
                                <span
                                    class="ml-auto font-mono text-[9px] tracking-[0.2em] text-accent uppercase"
                                    >→ vandaag</span
                                >
                            </div>
                            <div class="flex items-center gap-2 text-sm">
                                <span
                                    class="size-4 rounded-full border border-input"
                                />
                                <span class="font-medium">Inkopen voor de week</span>
                            </div>
                            <div class="flex items-center gap-2 text-sm">
                                <span
                                    class="size-4 rounded-full border border-input"
                                />
                                <span class="font-medium">Inertia v3 uitlezen</span>
                                <span
                                    class="ml-auto font-mono text-[9px] tracking-[0.2em] text-muted-foreground uppercase"
                                    >(side-project)</span
                                >
                            </div>
                            <div class="mt-auto border-t border-border/50 pt-3">
                                <input
                                    type="text"
                                    placeholder="Type om toe te voegen…"
                                    disabled
                                    class="w-full bg-transparent text-sm outline-none placeholder:text-muted-foreground/60"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- problem section -->
        <section class="border-b border-border/60 bg-card/40">
            <div
                class="mx-auto grid max-w-6xl gap-10 px-6 py-20 sm:px-8 lg:grid-cols-[1fr_1.4fr] sm:py-24"
            >
                <div class="flex flex-col gap-3">
                    <span
                        class="font-mono text-[10px] tracking-[0.3em] text-muted-foreground uppercase"
                    >
                        het probleem
                    </span>
                    <h2
                        class="text-3xl leading-tight font-bold tracking-tight sm:text-4xl"
                    >
                        Een todo per app, een lijst per moment, en nooit
                        <span class="brand-dot">overzicht</span>
                    </h2>
                </div>
                <div
                    class="flex flex-col gap-6 text-lg leading-relaxed text-muted-foreground"
                >
                    <p>
                        Mijn dag begint nooit met een schone lei. Een todo
                        staat half in mijn notitie-app, eentje op een post-it,
                        drie in een Slack-bericht aan mezelf. Bij elke nieuwe
                        lijst ontstaat een nieuwe silo — en daarmee de kans
                        iets te vergeten.
                    </p>
                    <p class="text-foreground">
                        Wat ik wilde: <strong>één plek</strong> waar
                        <em>alles</em> staat, met <strong>focus per dag</strong>
                        zonder dat de boel uit elkaar valt.
                    </p>
                </div>
            </div>
        </section>

        <!-- pillars -->
        <section>
            <div class="mx-auto max-w-6xl px-6 py-20 sm:px-8 sm:py-24">
                <div class="mb-12 flex flex-col gap-3">
                    <span
                        class="font-mono text-[10px] tracking-[0.3em] text-muted-foreground uppercase"
                    >
                        anders dan andere
                    </span>
                    <h2
                        class="max-w-2xl text-3xl leading-tight font-bold tracking-tight sm:text-4xl"
                    >
                        Vier ideeën die de app vormgeven
                    </h2>
                </div>

                <div class="grid gap-6 md:grid-cols-2">
                    <article
                        class="flex flex-col gap-3 rounded-2xl border border-border bg-card p-6 transition-colors hover:border-accent/40"
                    >
                        <div class="flex items-baseline justify-between">
                            <Inbox class="size-5 text-muted-foreground" />
                            <span
                                class="font-mono text-3xl leading-none font-bold text-muted-foreground/20"
                                >01</span
                            >
                        </div>
                        <h3 class="text-xl font-bold tracking-tight">
                            Eén todo, meerdere lijsten
                        </h3>
                        <p class="text-sm leading-relaxed text-muted-foreground">
                            Een todo leeft één keer in de database. Lijsten
                            zijn <em>verwijzingen</em>, geen kopieën. Vink af
                            in je daglijst → klaar in master. Voeg toe aan je
                            daglijst → automatisch ook in master.
                        </p>
                    </article>

                    <article
                        class="flex flex-col gap-3 rounded-2xl border border-border bg-card p-6 transition-colors hover:border-accent/40"
                    >
                        <div class="flex items-baseline justify-between">
                            <Sun class="size-5 text-muted-foreground" />
                            <span
                                class="font-mono text-3xl leading-none font-bold text-muted-foreground/20"
                                >02</span
                            >
                        </div>
                        <h3 class="text-xl font-bold tracking-tight">
                            Ochtendritueel, geen lege bak
                        </h3>
                        <p class="text-sm leading-relaxed text-muted-foreground">
                            Open de app op een werkdag en je krijgt drie
                            vragen: meenemen van gisteren, oppakken uit master,
                            of toevoegen wat nieuw is. Geen passieve todo-bak —
                            een actieve planning.
                        </p>
                    </article>

                    <article
                        class="flex flex-col gap-3 rounded-2xl border border-border bg-card p-6 transition-colors hover:border-accent/40"
                    >
                        <div class="flex items-baseline justify-between">
                            <Keyboard class="size-5 text-muted-foreground" />
                            <span
                                class="font-mono text-3xl leading-none font-bold text-muted-foreground/20"
                                >03</span
                            >
                        </div>
                        <h3 class="text-xl font-bold tracking-tight">
                            Quick-add met werkdag-besef
                        </h3>
                        <p class="text-sm leading-relaxed text-muted-foreground">
                            <kbd
                                class="rounded border border-input bg-background px-1.5 py-0.5 font-mono text-[11px]"
                                >⌘K</kbd
                            >
                            opent een snelle input. Op een werkdag landt het op
                            vandaag — in het weekend automatisch op maandag.
                            Zaterdag is geen werkdag.
                        </p>
                    </article>

                    <article
                        class="flex flex-col gap-3 rounded-2xl border border-border bg-card p-6 transition-colors hover:border-accent/40"
                    >
                        <div class="flex items-baseline justify-between">
                            <MousePointer2
                                class="size-5 text-muted-foreground"
                            />
                            <span
                                class="font-mono text-3xl leading-none font-bold text-muted-foreground/20"
                                >04</span
                            >
                        </div>
                        <h3 class="text-xl font-bold tracking-tight">
                            Apple Reminders-niveau interactie
                        </h3>
                        <p class="text-sm leading-relaxed text-muted-foreground">
                            Inline toevoegen onderin elke lijst (focus blijft
                            staan). Drag-and-drop volgorde. Rechtermuisknop
                            opent een rijk contextmenu: prioriteit, tags,
                            datum, lijst-koppeling.
                        </p>
                    </article>
                </div>
            </div>
        </section>

        <!-- numbers strip -->
        <section class="border-y border-border/60 bg-card/40">
            <div
                class="mx-auto grid max-w-6xl grid-cols-2 gap-y-8 px-6 py-12 sm:grid-cols-4 sm:px-8"
            >
                <div class="flex flex-col gap-1">
                    <span
                        class="text-4xl leading-none font-bold tracking-tight"
                        >5</span
                    >
                    <span
                        class="font-mono text-[10px] tracking-[0.25em] text-muted-foreground uppercase"
                        >sort modes per lijst</span
                    >
                </div>
                <div class="flex flex-col gap-1">
                    <span
                        class="text-4xl leading-none font-bold tracking-tight"
                        >∞</span
                    >
                    <span
                        class="font-mono text-[10px] tracking-[0.25em] text-muted-foreground uppercase"
                        >daglijsten</span
                    >
                </div>
                <div class="flex flex-col gap-1">
                    <span
                        class="text-4xl leading-none font-bold tracking-tight"
                        >3</span
                    >
                    <span
                        class="font-mono text-[10px] tracking-[0.25em] text-muted-foreground uppercase"
                        >quick-add manieren</span
                    >
                </div>
                <div class="flex flex-col gap-1">
                    <span
                        class="text-4xl leading-none font-bold tracking-tight brand-dot"
                        >1</span
                    >
                    <span
                        class="font-mono text-[10px] tracking-[0.25em] text-muted-foreground uppercase"
                        >master als bron</span
                    >
                </div>
            </div>
        </section>

        <!-- workflow -->
        <section class="border-b border-border/60">
            <div class="mx-auto max-w-6xl px-6 py-20 sm:px-8 sm:py-24">
                <div class="mb-10 flex flex-col gap-3">
                    <span
                        class="font-mono text-[10px] tracking-[0.3em] text-muted-foreground uppercase"
                    >
                        ochtendritueel
                    </span>
                    <h2
                        class="max-w-3xl text-3xl leading-tight font-bold tracking-tight sm:text-4xl"
                    >
                        Een werkdag begint met
                        <span class="brand-dot">drie vragen</span>
                    </h2>
                </div>

                <ol class="grid gap-4 sm:grid-cols-3">
                    <li
                        class="flex flex-col gap-2 rounded-xl border border-border/70 bg-card p-5"
                    >
                        <span
                            class="font-mono text-[10px] tracking-[0.25em] text-muted-foreground uppercase"
                            >stap 1</span
                        >
                        <h3 class="text-lg font-bold tracking-tight">
                            Meenemen?
                        </h3>
                        <p
                            class="text-sm leading-relaxed text-muted-foreground"
                        >
                            Niet-afgemaakte todos van de vorige werkdag staan
                            klaar — vink aan wat je vandaag wil oppakken.
                        </p>
                    </li>
                    <li
                        class="flex flex-col gap-2 rounded-xl border border-border/70 bg-card p-5"
                    >
                        <span
                            class="font-mono text-[10px] tracking-[0.25em] text-muted-foreground uppercase"
                            >stap 2</span
                        >
                        <h3 class="text-lg font-bold tracking-tight">
                            Uit master?
                        </h3>
                        <p
                            class="text-sm leading-relaxed text-muted-foreground"
                        >
                            Kies items uit je totaal-lijst — een knipoog naar
                            wat er nog ligt zonder dat je het hele archief in
                            moet duiken.
                        </p>
                    </li>
                    <li
                        class="flex flex-col gap-2 rounded-xl border border-border/70 bg-card p-5"
                    >
                        <span
                            class="font-mono text-[10px] tracking-[0.25em] text-muted-foreground uppercase"
                            >stap 3</span
                        >
                        <h3 class="text-lg font-bold tracking-tight">
                            Nieuw vandaag?
                        </h3>
                        <p
                            class="text-sm leading-relaxed text-muted-foreground"
                        >
                            Voeg toe wat alleen vandaag belangrijk is — landt
                            automatisch ook in master, zodat niets verloren
                            gaat.
                        </p>
                    </li>
                </ol>
            </div>
        </section>

        <!-- tech -->
        <section class="border-b border-border/60">
            <div
                class="mx-auto grid max-w-6xl gap-10 px-6 py-16 sm:px-8 lg:grid-cols-[1.4fr_1fr]"
            >
                <div class="flex flex-col gap-3">
                    <span
                        class="font-mono text-[10px] tracking-[0.3em] text-muted-foreground uppercase"
                    >
                        onder de motorkap
                    </span>
                    <h2
                        class="text-2xl leading-tight font-bold tracking-tight sm:text-3xl"
                    >
                        Laravel + Inertia + Vue.<br />
                        <span class="text-muted-foreground"
                            >Klaar voor een iOS-app later.</span
                        >
                    </h2>
                </div>
                <div
                    class="flex flex-col gap-3 font-mono text-[11px] tracking-wider text-muted-foreground uppercase"
                >
                    <div class="flex justify-between border-b border-border/60 py-2">
                        <span>backend</span>
                        <span class="text-foreground">Laravel 13 / PHP 8.4</span>
                    </div>
                    <div class="flex justify-between border-b border-border/60 py-2">
                        <span>frontend</span>
                        <span class="text-foreground"
                            >Vue 3 / Inertia / Tailwind 4</span
                        >
                    </div>
                    <div class="flex justify-between border-b border-border/60 py-2">
                        <span>architectuur</span>
                        <span class="text-foreground"
                            >action classes · dunne controllers</span
                        >
                    </div>
                    <div class="flex justify-between py-2">
                        <span>volgende</span>
                        <span class="text-accent">iOS native (later)</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- final cta -->
        <section class="bg-accent text-accent-foreground">
            <div
                class="mx-auto flex max-w-6xl flex-col items-start gap-6 px-6 py-20 sm:px-8 sm:py-24"
            >
                <span
                    class="font-mono text-[10px] tracking-[0.3em] uppercase opacity-80"
                >
                    begin vandaag
                </span>
                <h2
                    class="max-w-3xl text-4xl leading-tight font-bold tracking-tight sm:text-6xl"
                >
                    Stop met scattered todos.<br />
                    Begin met focus.
                </h2>
                <p class="max-w-prose text-lg leading-relaxed opacity-90">
                    Gratis account, geen creditcard. Bouw je master op, plan
                    je dag, en zie hoe alles in sync blijft.
                </p>
                <div class="flex flex-wrap items-center gap-3 pt-2">
                    <Link
                        :href="register()"
                        class="inline-flex items-center gap-2 rounded-lg bg-foreground px-5 py-3 font-medium text-background transition-opacity hover:opacity-90"
                    >
                        Maak een account
                        <ArrowRight class="size-4" />
                    </Link>
                    <Link
                        :href="login()"
                        class="inline-flex items-center gap-2 rounded-lg border border-accent-foreground/30 px-5 py-3 font-medium hover:bg-accent-foreground/10"
                    >
                        Heb al een account
                    </Link>
                </div>
            </div>
        </section>

        <!-- footer -->
        <footer class="bg-background">
            <div
                class="mx-auto flex max-w-6xl flex-col items-start justify-between gap-4 px-6 py-10 sm:flex-row sm:items-center sm:px-8"
            >
                <div class="flex items-center gap-3">
                    <CalendarDays
                        class="size-4 text-muted-foreground"
                    />
                    <span
                        class="brand-dot text-base leading-none font-bold tracking-tight"
                        >Tododeloo</span
                    >
                </div>
                <div
                    class="flex items-center gap-4 font-mono text-[10px] tracking-[0.25em] text-muted-foreground uppercase"
                >
                    <span>built in nl</span>
                    <span class="text-muted-foreground/40">·</span>
                    <span>v0.1</span>
                </div>
            </div>
        </footer>
    </div>
</template>

<?php

use App\Support\DateParsing\DutchDateParser;
use App\Support\RecurrencePresets;
use Carbon\CarbonImmutable;

/** Reference "today" = Wednesday 20 May 2026. This week's Monday = 2026-05-18. */
function parseNL(string $input): array
{
    return (new DutchDateParser)->parse($input, CarbonImmutable::parse('2026-05-20'));
}

it('parses a one-off date and keeps the cleaned title', function (string $input, ?string $date, string $title) {
    $result = parseNL($input);

    expect($result['date']?->toDateString())->toBe($date)
        ->and($result['title'])->toBe($title)
        ->and($result['recurrence'])->toBeNull();
})->with([
    'volgende week dinsdag' => ['volgende week dinsdag ff x doen', '2026-05-26', 'ff x doen'],
    'morgen + reminder filler' => ['morgen herinneren aan eten opruimen', '2026-05-21', 'eten opruimen'],
    'volgende werkdag' => ['volgende werkdag bellen naar frank', '2026-05-21', 'bellen naar frank'],
    'over 2 dagen' => ['over 2 dagen tandarts', '2026-05-22', 'tandarts'],
    'over een weekje' => ['over een weekje sporten', '2026-05-27', 'sporten'],
    'kan je me prefix' => ['kan je me morgen de hond uitlaten', '2026-05-21', 'de hond uitlaten'],
    'overmorgen' => ['overmorgen tandarts bellen', '2026-05-22', 'tandarts bellen'],
    'bare weekday this week' => ['vrijdag rapport afmaken', '2026-05-22', 'rapport afmaken'],
    'op weekday already past' => ['bel frank op dinsdag', '2026-05-26', 'bel frank'],
    'deze week weekday' => ['deze week donderdag verslag', '2026-05-21', 'verslag'],
    'over 3 weken' => ['over 3 weken vakantie plannen', '2026-06-10', 'vakantie plannen'],
    'over N weken + weekday' => ['over 2 weken vrijdag een etentje organiseren', '2026-06-05', 'een etentje organiseren'],
    'over N weken op weekday' => ['over een week op maandag de auto wassen', '2026-05-25', 'de auto wassen'],
    'spelled-out date' => ['op 25 december kerstcadeaus kopen', '2026-12-25', 'kerstcadeaus kopen'],
    'numeric date' => ['25-12 oliebollen', '2026-12-25', 'oliebollen'],
    'vandaag keeps ff' => ['vandaag ff de planten water geven', '2026-05-20', 'ff de planten water geven'],
    'komende week' => ['komende week vergaderen', '2026-05-25', 'vergaderen'],
    'no date at all' => ['boodschappen doen', null, 'boodschappen doen'],
    'verb maak is kept' => ['maak ontbijt', null, 'maak ontbijt'],
    'time is stripped' => ['vrijdag om 15:00 bellen', '2026-05-22', 'bellen'],
    'time om N uur' => ['morgen om 3 uur tandarts', '2026-05-21', 'tandarts'],
    'time only, no date' => ['bel klant om 14:30', null, 'bel klant'],
    'duration is kept' => ['3 uur durende meeting plannen', null, '3 uur durende meeting plannen'],
    'over het weekend' => ['over het weekend klussen', '2026-05-23', 'klussen'],
    'volgende maand de 1e' => ['volgende maand de 1e huur betalen', '2026-06-01', 'huur betalen'],
    'de Ne van volgende maand' => ['de 15e van volgende maand factuur sturen', '2026-06-15', 'factuur sturen'],
    'over een maand + taak' => ['over een maand de planten verpotten', '2026-06-20', 'de planten verpotten'],
    'reminder zonder taak → Herinnering' => ['herinner me dinsdag', '2026-05-26', 'Herinnering'],
    'reminder zonder taak (morgen)' => ['herinner mij morgen', '2026-05-21', 'Herinnering'],
    'reminder zonder taak (over een maand)' => ['herinner me over een maand', '2026-06-20', 'Herinnering'],
    'onthoud is gestript' => ['onthoud dinsdag tandarts', '2026-05-26', 'tandarts'],
    'reminder-woord is gestript' => ['reminder dinsdag tandarts bellen', '2026-05-26', 'tandarts bellen'],
]);

it('parses recurrence phrases into an rrule + anchor and a cleaned title', function (string $input, string $rrule, string $anchor, string $title) {
    $result = parseNL($input);

    expect($result['recurrence'])->not->toBeNull()
        ->and($result['recurrence']['rrule'])->toBe($rrule)
        ->and($result['recurrence']['anchor']->toDateString())->toBe($anchor)
        ->and($result['title'])->toBe($title)
        ->and($result['date'])->toBeNull();
})->with([
    'iedere week → Monday' => ['herinner mij iedere week aan de planning', 'FREQ=WEEKLY;BYDAY=MO', '2026-05-25', 'de planning'],
    'maak + quotes' => ['maak iedere dinsdag een todo aan voor "test title!"', 'FREQ=WEEKLY;BYDAY=TU', '2026-05-26', 'test title!'],
    'elke werkdag' => ['elke werkdag stand-up', 'FREQ=WEEKLY;BYDAY=MO,TU,WE,TH,FR', '2026-05-20', 'stand-up'],
    'elke dag' => ['elke dag mediteren', 'FREQ=DAILY', '2026-05-20', 'mediteren'],
    'maandelijks' => ['elke maand huur betalen', 'FREQ=MONTHLY', '2026-05-20', 'huur betalen'],
    'jaarlijks' => ['elk jaar verjaardag vieren', 'FREQ=YEARLY', '2026-05-20', 'verjaardag vieren'],
    'om de week' => ['om de week planten water geven', 'FREQ=WEEKLY;INTERVAL=2;BYDAY=MO', '2026-05-25', 'planten water geven'],
    'elke 2 dagen' => ['elke 2 dagen pillen innemen', 'FREQ=DAILY;INTERVAL=2', '2026-05-20', 'pillen innemen'],
    'reminder zonder taak → herhaling' => ['herinner me elke dinsdag', 'FREQ=WEEKLY;BYDAY=TU', '2026-05-26', 'Herinnering'],
    // Nth weekday of the month → MONTHLY;BYDAY=nXX, anchored on the next such date.
    'eerste dinsdag van de maand' => ['iedere eerste dinsdag van de maand eten geven aan de hond', 'FREQ=MONTHLY;BYDAY=1TU', '2026-06-02', 'eten geven aan de hond'],
    'laatste vrijdag van de maand' => ['elke laatste vrijdag van de maand rapport sturen', 'FREQ=MONTHLY;BYDAY=-1FR', '2026-05-29', 'rapport sturen'],
    // Nth weekday of the quarter → MONTHLY;INTERVAL=3 pinned to a quarter start.
    'eerste vrijdag van het kwartaal' => ['iedere eerste vrijdag van het kwartaal de cijfers checken', 'FREQ=MONTHLY;INTERVAL=3;BYDAY=1FR', '2026-07-03', 'de cijfers checken'],
    // Workday / daily with excluded weekdays.
    'werkdag behalve maandag' => ['iedere werkdag behalve maandag de planten water geven', 'FREQ=WEEKLY;BYDAY=TU,WE,TH,FR', '2026-05-20', 'de planten water geven'],
    'elke dag behalve zondag' => ['elke dag behalve zondag pillen innemen', 'FREQ=WEEKLY;BYDAY=MO,TU,WE,TH,FR,SA', '2026-05-20', 'pillen innemen'],
]);

it('describes the nth-weekday recurrence in Dutch', function () {
    $presets = new RecurrencePresets;

    $monthly = parseNL('iedere eerste dinsdag van de maand x')['recurrence'];
    expect($presets->describe($monthly['rrule'], $monthly['anchor'])['label'])
        ->toBe('Maandelijks op de 1e dinsdag');

    $quarterly = parseNL('iedere eerste vrijdag van het kwartaal x')['recurrence'];
    expect($presets->describe($quarterly['rrule'], $quarterly['anchor'])['label'])
        ->toBe('Elke 3 maanden op de 1e vrijdag');
});

it('produces rrules that match the recurrence presets', function () {
    // The "elke werkdag" / "elke dinsdag" / "elke dag" rrules should equal what
    // RecurrencePresets builds, so the readable summary lights up.
    $presets = new RecurrencePresets;

    $weekdays = parseNL('elke werkdag x')['recurrence'];
    expect($presets->describe($weekdays['rrule'], $weekdays['anchor'])['preset'])->toBe('weekdays');

    $tuesday = parseNL('elke dinsdag x')['recurrence'];
    expect($presets->describe($tuesday['rrule'], $tuesday['anchor'])['preset'])->toBe('weekly');
});

function annotateNL(string $input): array
{
    return app(DutchDateParser::class)->annotate($input, CarbonImmutable::parse('2026-05-20'));
}

it('annotates a sentence into highlightable segments that reproduce the input', function () {
    $result = annotateNL('maak een todo aan voor dinsdag dat ik de lamp moet aanpassen');

    expect($result['date']['iso'])->toBe('2026-05-26');

    $segments = collect($result['segments']);

    // Concatenating every segment reproduces the (normalized) input verbatim.
    expect($segments->pluck('text')->implode(''))->toBe($result['input']);

    $date = $segments->firstWhere('type', 'date');
    expect($date)->not->toBeNull()
        ->and($date['text'])->toContain('dinsdag')
        ->and($date['resolved'])->toContain('dinsdag');

    expect($segments->pluck('type'))->toContain('title');
});

it('marks an undated task entirely as title', function () {
    $result = annotateNL('boodschappen doen');

    expect($result['date'])->toBeNull()
        ->and($result['recurrence'])->toBeNull();

    $segments = collect($result['segments']);
    expect($segments->pluck('type')->unique()->values()->all())->toBe(['title'])
        ->and($segments->pluck('text')->implode(''))->toBe('boodschappen doen');
});

it('annotates a recurrence phrase with its summary', function () {
    $result = annotateNL('elke werkdag stand-up');

    expect($result['recurrence']['rrule'])->toBe('FREQ=WEEKLY;BYDAY=MO,TU,WE,TH,FR');

    $segments = collect($result['segments']);
    $recurrence = $segments->firstWhere('type', 'recurrence');
    expect($recurrence)->not->toBeNull()
        ->and($recurrence['text'])->toContain('werkdag')
        ->and($recurrence['resolved'])->toContain('werkdag')
        ->and($segments->pluck('text')->implode(''))->toBe($result['input']);
});

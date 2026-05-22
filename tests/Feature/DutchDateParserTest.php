<?php

use App\Support\DutchDateParser;
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
]);

it('produces rrules that match the recurrence presets', function () {
    // The "elke werkdag" / "elke dinsdag" / "elke dag" rrules should equal what
    // RecurrencePresets builds, so the readable summary lights up.
    $presets = new RecurrencePresets;

    $weekdays = parseNL('elke werkdag x')['recurrence'];
    expect($presets->describe($weekdays['rrule'], $weekdays['anchor'])['preset'])->toBe('weekdays');

    $tuesday = parseNL('elke dinsdag x')['recurrence'];
    expect($presets->describe($tuesday['rrule'], $tuesday['anchor'])['preset'])->toBe('weekly');
});

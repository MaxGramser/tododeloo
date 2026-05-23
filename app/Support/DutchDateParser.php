<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Best-effort Dutch natural-language parser for the quick-add field. Pulls a
 * one-off date or a recurrence out of a free-text line and returns the leftover
 * as the todo title, stripping reminder/command filler ("kan je me",
 * "herinner mij aan", "maak een todo voor", …).
 *
 * It is deliberately rule-based and ordered (specific → general); it does not
 * try to be a full NLP engine, just smart enough for everyday phrasing. The
 * produced RRULEs match App\Support\RecurrencePresets so the readable summary
 * ("Elke werkdag") lights up automatically.
 */
class DutchDateParser
{
    /** Full Dutch weekday names → ISO weekday (Mon=1 … Sun=7). */
    private const WEEKDAYS = [
        'maandag' => 1, 'dinsdag' => 2, 'woensdag' => 3, 'donderdag' => 4,
        'vrijdag' => 5, 'zaterdag' => 6, 'zondag' => 7,
    ];

    private const DAY_CODE = [1 => 'MO', 2 => 'TU', 3 => 'WE', 4 => 'TH', 5 => 'FR', 6 => 'SA', 7 => 'SU'];

    private const MONTHS = [
        'januari' => 1, 'februari' => 2, 'maart' => 3, 'april' => 4, 'mei' => 5, 'juni' => 6,
        'juli' => 7, 'augustus' => 8, 'september' => 9, 'oktober' => 10, 'november' => 11, 'december' => 12,
        'jan' => 1, 'feb' => 2, 'mrt' => 3, 'apr' => 4, 'jun' => 6, 'jul' => 7, 'aug' => 8,
        'sep' => 9, 'sept' => 9, 'okt' => 10, 'nov' => 11, 'dec' => 12,
    ];

    private const NUMBER_WORDS = [
        'een' => 1, 'één' => 1, 'twee' => 2, 'drie' => 3, 'vier' => 4, 'vijf' => 5,
        'zes' => 6, 'zeven' => 7, 'acht' => 8, 'negen' => 9, 'tien' => 10,
        'elf' => 11, 'twaalf' => 12, 'paar' => 2,
    ];

    private const WEEKDAY_RE = 'maandag|dinsdag|woensdag|donderdag|vrijdag|zaterdag|zondag';

    private const NUMBER_RE = '\d+|een|één|twee|drie|vier|vijf|zes|zeven|acht|negen|tien|elf|twaalf|paar';

    /**
     * While true, `cut()` remembers the matched date/recurrence phrase so
     * `annotate()` can map it back onto the original sentence for highlighting.
     */
    private bool $recording = false;

    /** @var array{text: string, kind: string}|null */
    private ?array $matchedPhrase = null;

    /** Which bucket the currently-running extractor records into. */
    private string $currentKind = 'date';

    public function __construct(private ?RecurrencePresets $presets = null) {}

    /**
     * @return array{title: string, date: ?CarbonImmutable, recurrence: ?array{rrule: string, anchor: CarbonImmutable}}
     */
    public function parse(string $input, ?CarbonImmutable $today = null): array
    {
        $today = ($today ?? CarbonImmutable::today())->startOfDay();

        $text = $this->normalize($input);
        $isReminder = $this->looksLikeReminder($text);
        $text = $this->stripLeadingCommands($text);
        $text = $this->stripTimes($text);

        [$recurrence, $text] = $this->extractRecurrence($text, $today);

        $date = null;
        if ($recurrence === null) {
            [$date, $text] = $this->extractDate($text, $today);
        }

        if ($recurrence !== null || $date !== null) {
            $text = $this->stripLeadingCommands($text);
            $text = $this->stripConnectors($text);
        }

        $title = $this->cleanup($text);

        if ($title === '') {
            // A bare reminder ("herinner me dinsdag") leaves no task text once the
            // filler and date are stripped, but the scheduling intent is clear —
            // keep the date/recurrence under a generic title instead of dropping it.
            if ($isReminder && ($date !== null || $recurrence !== null)) {
                return ['title' => 'Herinnering', 'date' => $date, 'recurrence' => $recurrence];
            }

            return ['title' => trim($input), 'date' => null, 'recurrence' => null];
        }

        return ['title' => $title, 'date' => $date, 'recurrence' => $recurrence];
    }

    private function normalize(string $input): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $input));
    }

    // MARK: - Recurrence

    /**
     * @return array{0: ?array{rrule: string, anchor: CarbonImmutable}, 1: string}
     */
    private function extractRecurrence(string $text, CarbonImmutable $today): array
    {
        $this->currentKind = 'recurrence';
        $every = '(?:elke|iedere|elk|ieder|alle)';

        // Every workday.
        if ($this->find($text, '/\b'.$every.'\s+werkdag(?:en)?\b/iu', $m) || $this->find($text, '/\bop\s+werkdagen\b/iu', $m)) {
            return [$this->rec('FREQ=WEEKLY;BYDAY=MO,TU,WE,TH,FR', Workday::quickAddTargetDate($today)), $this->cut($text, $m)];
        }

        // Every <weekday>.
        if ($this->find($text, '/\b'.$every.'\s+('.self::WEEKDAY_RE.')\b/iu', $m)) {
            $iso = self::WEEKDAYS[mb_strtolower($m[1][0])];
            $anchor = $this->upcomingWeekday($today, $iso);

            return [$this->rec('FREQ=WEEKLY;BYDAY='.self::DAY_CODE[$iso], $anchor), $this->cut($text, $m)];
        }

        // Every N days / om de dag.
        if ($this->find($text, '/\b(?:'.$every.'|om de)\s+('.self::NUMBER_RE.')\s+dag(?:en)?\b/iu', $m)) {
            return [$this->rec($this->daily($this->num($m[1][0])), $today), $this->cut($text, $m)];
        }
        if ($this->find($text, '/\bom\s+de\s+dag\b/iu', $m)) {
            return [$this->rec('FREQ=DAILY;INTERVAL=2', $today), $this->cut($text, $m)];
        }

        // Every day / daily.
        if ($this->find($text, '/\b'.$every.'\s+dag\b/iu', $m) || $this->find($text, '/\bdagelijks\b/iu', $m)) {
            return [$this->rec('FREQ=DAILY', $today), $this->cut($text, $m)];
        }

        // Every N weeks / om de N weken.
        if ($this->find($text, '/\b(?:'.$every.'|om de)\s+('.self::NUMBER_RE.')\s+weken\b/iu', $m)) {
            return [$this->rec($this->weekly($this->num($m[1][0])), $this->upcomingWeekday($today, 1)), $this->cut($text, $m)];
        }
        // Om de week / tweewekelijks.
        if ($this->find($text, '/\bom\s+de\s+week\b/iu', $m) || $this->find($text, '/\btweewekelijks\b/iu', $m)) {
            return [$this->rec('FREQ=WEEKLY;INTERVAL=2;BYDAY=MO', $this->upcomingWeekday($today, 1)), $this->cut($text, $m)];
        }
        // Every week / weekly → defaults to Monday.
        if ($this->find($text, '/\b'.$every.'\s+week\b/iu', $m) || $this->find($text, '/\bwekelijks\b/iu', $m) || $this->find($text, '/\bper\s+week\b/iu', $m)) {
            return [$this->rec('FREQ=WEEKLY;BYDAY=MO', $this->upcomingWeekday($today, 1)), $this->cut($text, $m)];
        }

        // Half-yearly.
        if ($this->find($text, '/\b'.$every.'\s+half\s+jaar\b/iu', $m) || $this->find($text, '/\bhalfjaarlijks\b/iu', $m)) {
            return [$this->rec('FREQ=MONTHLY;INTERVAL=6', $today), $this->cut($text, $m)];
        }
        // Every N months.
        if ($this->find($text, '/\b(?:'.$every.'|om de)\s+('.self::NUMBER_RE.')\s+maanden\b/iu', $m)) {
            return [$this->rec($this->monthly($this->num($m[1][0])), $today), $this->cut($text, $m)];
        }
        // Every month / monthly.
        if ($this->find($text, '/\b'.$every.'\s+maand\b/iu', $m) || $this->find($text, '/\bmaandelijks\b/iu', $m) || $this->find($text, '/\bper\s+maand\b/iu', $m)) {
            return [$this->rec('FREQ=MONTHLY', $today), $this->cut($text, $m)];
        }
        // Every year / yearly.
        if ($this->find($text, '/\b'.$every.'\s+jaar\b/iu', $m) || $this->find($text, '/\bjaarlijks\b/iu', $m) || $this->find($text, '/\bper\s+jaar\b/iu', $m)) {
            return [$this->rec('FREQ=YEARLY', $today), $this->cut($text, $m)];
        }

        return [null, $text];
    }

    // MARK: - One-off date

    /**
     * @return array{0: ?CarbonImmutable, 1: string}
     */
    private function extractDate(string $text, CarbonImmutable $today): array
    {
        $this->currentKind = 'date';
        $wd = self::WEEKDAY_RE;
        $num = self::NUMBER_RE;
        $month = implode('|', array_keys(self::MONTHS));

        // volgende/komende week <weekday> | <weekday> volgende week
        if ($this->find($text, '/\b(?:volgende|komende)\s+week\s+('.$wd.')\b/iu', $m)
            || $this->find($text, '/\b('.$wd.')\s+(?:volgende|komende)\s+week\b/iu', $m)) {
            return [$this->weekOffsetWeekday($today, 1, self::WEEKDAYS[mb_strtolower($m[1][0])]), $this->cut($text, $m)];
        }

        // deze week <weekday>
        if ($this->find($text, '/\bdeze\s+week\s+('.$wd.')\b/iu', $m)) {
            return [$this->weekOffsetWeekday($today, 0, self::WEEKDAYS[mb_strtolower($m[1][0])]), $this->cut($text, $m)];
        }

        // over N dagen / weken / maanden / jaar
        if ($this->find($text, '/\bover\s+('.$num.')\s+dag(?:en|je)?\b/iu', $m)) {
            return [$today->addDays($this->num($m[1][0])), $this->cut($text, $m)];
        }
        if ($this->find($text, '/\bover\s+('.$num.')\s+(?:weken|week|weekje)\b/iu', $m)) {
            return [$today->addWeeks($this->num($m[1][0])), $this->cut($text, $m)];
        }
        if ($this->find($text, '/\bover\s+('.$num.')\s+maand(?:en)?\b/iu', $m)) {
            return [$today->addMonths($this->num($m[1][0])), $this->cut($text, $m)];
        }
        if ($this->find($text, '/\bover\s+('.$num.')\s+ja(?:ar|ren)\b/iu', $m)) {
            return [$today->addYears($this->num($m[1][0])), $this->cut($text, $m)];
        }

        // begin/eind (van) volgende week
        if ($this->find($text, '/\b(?:begin|aan het begin)\s+(?:van\s+)?(?:de\s+)?(?:volgende|komende)\s+week\b/iu', $m)) {
            return [$this->weekOffsetWeekday($today, 1, 1), $this->cut($text, $m)];
        }
        if ($this->find($text, '/\b(?:eind|einde|aan het eind)\s+(?:van\s+)?(?:de\s+)?(?:volgende|komende)\s+week\b/iu', $m)) {
            return [$this->weekOffsetWeekday($today, 1, 5), $this->cut($text, $m)];
        }
        if ($this->find($text, '/\b(?:eind|einde)\s+(?:van\s+)?(?:de\s+)?(?:deze\s+)?week\b/iu', $m)) {
            return [$this->weekOffsetWeekday($today, 0, 5), $this->cut($text, $m)];
        }

        // volgende/komende week (no weekday) → first workday next week (Monday)
        if ($this->find($text, '/\b(?:volgende|komende)\s+week\b/iu', $m)) {
            return [$this->weekOffsetWeekday($today, 1, 1), $this->cut($text, $m)];
        }
        if ($this->find($text, '/\bdeze\s+week\b/iu', $m)) {
            return [$today, $this->cut($text, $m)];
        }

        // volgende maand de <day> | (op) de <day> van (deze|volgende) maand
        if ($this->find($text, '/\bvolgende\s+maand\s+(?:op\s+)?de\s+(\d{1,2}|eerste|laatste)(?:e|ste|de)?\b/iu', $m)) {
            return [$this->dayOfMonth($today->addMonth(), $m[1][0]), $this->cut($text, $m)];
        }
        if ($this->find($text, '/\b(?:op\s+)?de\s+(\d{1,2}|eerste|laatste)(?:e|ste|de)?\s+van\s+(?:de\s+)?(deze|volgende|komende)\s+maand\b/iu', $m)) {
            $base = mb_strtolower($m[2][0]) === 'deze' ? $today : $today->addMonth();

            return [$this->dayOfMonth($base, $m[1][0]), $this->cut($text, $m)];
        }

        if ($this->find($text, '/\bvolgende\s+maand\b/iu', $m)) {
            return [$today->addMonth(), $this->cut($text, $m)];
        }
        if ($this->find($text, '/\bvolgend(?:e)?\s+jaar\b/iu', $m)) {
            return [$today->addYear(), $this->cut($text, $m)];
        }

        // next workday
        if ($this->find($text, '/\b(?:eerstvolgende|eerst volgende|volgende|komende)\s+werkdag\b/iu', $m)) {
            return [Workday::nextWorkdayAfter($today), $this->cut($text, $m)];
        }

        // qualified weekday: aanstaande/a.s./komende/volgende/deze <weekday>
        if ($this->find($text, '/\b(aanstaande|a\.?s\.?|komende|komend|volgende|deze)\s+('.$wd.')\b/iu', $m)) {
            $qualifier = mb_strtolower($m[1][0]);
            $iso = self::WEEKDAYS[mb_strtolower($m[2][0])];
            $date = match (true) {
                $qualifier === 'deze' => $this->weekOffsetWeekday($today, 0, $iso),
                $qualifier === 'volgende' => $this->weekOffsetWeekday($today, 1, $iso),
                default => $this->upcomingWeekday($today, $iso),
            };

            return [$date, $this->cut($text, $m)];
        }

        // op <weekday>
        if ($this->find($text, '/\bop\s+('.$wd.')\b/iu', $m)) {
            return [$this->upcomingWeekday($today, self::WEEKDAYS[mb_strtolower($m[1][0])]), $this->cut($text, $m)];
        }

        // relative days
        if ($this->find($text, '/\bovermorgen\b/iu', $m)) {
            return [$today->addDays(2), $this->cut($text, $m)];
        }
        if ($this->find($text, '/\bmorgen\b/iu', $m)) {
            return [$today->addDay(), $this->cut($text, $m)];
        }
        if ($this->find($text, '/\beergisteren\b/iu', $m)) {
            return [$today->subDays(2), $this->cut($text, $m)];
        }
        if ($this->find($text, '/\bgisteren\b/iu', $m)) {
            return [$today->subDay(), $this->cut($text, $m)];
        }
        if ($this->find($text, '/\b(?:vandaag|vanmiddag|vanavond|vannacht|vanochtend|vanmorgen|straks|zometeen|zo meteen|nu|meteen)\b/iu', $m)) {
            return [$today, $this->cut($text, $m)];
        }

        // dit/komend weekend, over het weekend → upcoming Saturday
        if ($this->find($text, '/\b(?:dit|komend|komende|aankomend)\s+weekend\b/iu', $m)
            || $this->find($text, '/\b(?:in|over)\s+het\s+weekend\b/iu', $m)) {
            return [$this->upcomingWeekday($today, 6), $this->cut($text, $m)];
        }

        // [op] <day> <month> [year]
        if ($this->find($text, '/\b(?:op\s+)?(\d{1,2})(?:e|ste|de)?\s+('.$month.')\b(?:\s+(\d{4}))?/iu', $m)) {
            $day = (int) $m[1][0];
            $monthNum = self::MONTHS[mb_strtolower($m[2][0])];
            $year = isset($m[3]) && $m[3][0] !== '' ? (int) $m[3][0] : null;

            return [$this->buildDate($today, $day, $monthNum, $year), $this->cut($text, $m)];
        }

        // numeric date d-m[-y] / d/m / d.m
        if ($this->find($text, '/\b(\d{1,2})[-\/.](\d{1,2})(?:[-\/.](\d{2,4}))?\b/iu', $m)) {
            $day = (int) $m[1][0];
            $monthNum = (int) $m[2][0];
            if ($day >= 1 && $day <= 31 && $monthNum >= 1 && $monthNum <= 12) {
                $year = isset($m[3]) && $m[3][0] !== '' ? (int) $m[3][0] : null;
                if ($year !== null && $year < 100) {
                    $year += 2000;
                }

                return [$this->buildDate($today, $day, $monthNum, $year), $this->cut($text, $m)];
            }
        }

        // bare weekday
        if ($this->find($text, '/\b('.$wd.')\b/iu', $m)) {
            return [$this->upcomingWeekday($today, self::WEEKDAYS[mb_strtolower($m[1][0])]), $this->cut($text, $m)];
        }

        return [null, $text];
    }

    // MARK: - Filler stripping

    /** Whether the line is phrased as a reminder ("herinner me …", "onthoud …", "reminder …"). */
    private function looksLikeReminder(string $text): bool
    {
        return preg_match('/\b(?:herinner(?:ing|en)?|onthoud(?:en)?|reminder|memo|vergeet\s+niet|denk\s+(?:er)?aan)\b/iu', $text) === 1;
    }

    private function stripLeadingCommands(string $text): string
    {
        $patterns = [
            '/^(?:kun|kan)\s+(?:je|jij|u)\s+(?:me|mij|mn|m\'n|ons)?\s*/iu',
            '/^(?:zou|wil)\s+je\s+(?:misschien\s+|even\s+)?(?:me|mij)?\s*/iu',
            '/^herinner(?:ing|en)?\s*(?:me|mij|mn|ons)?\s*(?:er)?\s*(?:aan|voor|om|dat)?\s*/iu',
            '/^onthoud(?:en)?\s*(?:dat\s+(?:ik\s+)?|(?:om|te)\s+)?/iu',
            '/^reminder\s*[:\-–]?\s*(?:(?:voor|om)\s+)?/iu',
            '/^memo\s*[:\-–]?\s*/iu',
            '/^denk(?:en)?\s+(?:er)?aan\s+(?:om|te|dat)?\s*/iu',
            '/^vergeet\s+niet\s+(?:om|te|dat)?\s*/iu',
            '/^ik\s+(?:moet|wil|zou|ga|hoef)\s+(?:nog\s+)?/iu',
            '/^(?:we|wij)\s+(?:moeten|willen|gaan)\s+(?:nog\s+)?/iu',
            '/^moet\s+(?:ik\s+)?nog\s+/iu',
            '/^zorg\s+(?:er)?voor\s+dat\s+(?:ik\s+)?/iu',
            '/^zorg\s+dat\s+(?:ik\s+)?/iu',
            '/^(?:graag|alsjeblieft|alstublieft|aub|a\.u\.b\.)\s+/iu',
            '/^(?:to-?do|taak|task|notitie)\s*[:\-–]\s*/iu',
            '/^maak(?=.*\b(?:to-?do|taak|elke|iedere|dagelijks|wekelijks|maandelijks|jaarlijks)\b)\s+/iu',
            '/^plan(?:\s+in)?\s+om\s+/iu',
            '/^plan\s+in\s+/iu',
            '/^inplannen\s*[:\-]?\s*/iu',
        ];

        return $this->loopStrip($text, $patterns);
    }

    private function stripConnectors(string $text): string
    {
        $patterns = [
            '/^(?:een|de|het)\s+(?:nieuwe\s+)?(?:to-?do|taak)\s+(?:aan\s+)?(?:voor|om|van)\b\s*/iu',
            '/^(?:to-?do|taak)\s+(?:aan\s+)?(?:voor|om)\b\s*/iu',
            '/^(?:een|de|het)\s+(?:to-?do|taak)\b\s*/iu',
            '/^herinner(?:ing|en)?\s*(?:me|mij)?\s*(?:aan|voor|om)\b\s*/iu',
            '/^om\s+te\b\s*/iu',
            '/^(?:om|aan|voor|op|met|dat|dan|te|eraan|er\s+aan)\b\s*/iu',
        ];

        return $this->loopStrip($text, $patterns);
    }

    /**
     * Remove clock times — todos have no time field. Conservative: only matches
     * with an explicit "om", a colon, or "uur", so "3 uur durende meeting" stays.
     */
    private function stripTimes(string $text): string
    {
        $patterns = [
            '/\bom\s+half\s+\d{1,2}\b/iu',
            '/\bom\s+kwart\s+(?:over|voor)\s+\d{1,2}\b/iu',
            '/\btussen\s+\d{1,2}(?:[:.]\d{2})?\s*(?:en|-|tot)\s*\d{1,2}(?:[:.]\d{2})?\s*uur\b/iu',
            '/\bom\s+\d{1,2}[:.]\d{2}\s*(?:uur)?\b/iu',
            '/\bom\s+\d{1,2}\s*uur\b/iu',
            '/\b\d{1,2}[:.]\d{2}\s*uur\b/iu',
            '/\b\d{1,2}[:.]\d{2}\b/iu',
            "/'s\\s*(?:ochtends|middags|avonds|nachts)\\b/iu",
        ];

        return $this->loopStrip($text, $patterns);
    }

    /**
     * @param  list<string>  $patterns
     */
    private function loopStrip(string $text, array $patterns): string
    {
        do {
            $before = $text;
            foreach ($patterns as $pattern) {
                $text = (string) preg_replace($pattern, '', $text, 1);
            }
            $text = ltrim($text);
        } while ($text !== $before);

        return $text;
    }

    private function cleanup(string $text): string
    {
        $text = (string) preg_replace('/\s+/u', ' ', $text);
        $text = trim($text);
        // Strip a single layer of surrounding quotes.
        $text = (string) preg_replace('/^["\'“”‘’](.*)["\'“”‘’]$/u', '$1', $text);
        // Trim dangling separators (keep ! and ?).
        $text = (string) preg_replace('/^[\s:,\-–]+/u', '', $text);
        $text = (string) preg_replace('/[\s:,\-–]+$/u', '', $text);

        return trim($text);
    }

    // MARK: - Date helpers

    /** Upcoming occurrence of an ISO weekday, today included. */
    private function upcomingWeekday(CarbonImmutable $today, int $iso): CarbonImmutable
    {
        $monday = $today->startOfWeek(CarbonInterface::MONDAY);
        $candidate = $monday->addDays($iso - 1);

        return $candidate->lt($today) ? $candidate->addWeek() : $candidate;
    }

    /** A weekday in this week ($weeks=0) or a future week relative to today. */
    private function weekOffsetWeekday(CarbonImmutable $today, int $weeks, int $iso): CarbonImmutable
    {
        return $today->startOfWeek(CarbonInterface::MONDAY)->addWeeks($weeks)->addDays($iso - 1);
    }

    /** A day within $base's month: a number, "eerste" (1st) or "laatste" (last). */
    private function dayOfMonth(CarbonImmutable $base, string $token): CarbonImmutable
    {
        $token = mb_strtolower($token);

        if ($token === 'laatste') {
            return $base->endOfMonth()->startOfDay();
        }

        $day = $token === 'eerste' ? 1 : (int) $token;
        $day = max(1, min($day, $base->daysInMonth));

        return $base->day($day)->startOfDay();
    }

    private function buildDate(CarbonImmutable $today, int $day, int $month, ?int $year): CarbonImmutable
    {
        $date = CarbonImmutable::create($year ?? $today->year, $month, $day, 0, 0, 0, $today->timezone);

        // No explicit year and the date already passed → assume next year.
        if ($year === null && $date->lt($today)) {
            $date = $date->addYear();
        }

        return $date;
    }

    // MARK: - Small helpers

    /**
     * @param  array<int, array{0: string, 1: int}>|null  $m
     */
    private function find(string $text, string $pattern, ?array &$m): bool
    {
        return preg_match($pattern, $text, $m, PREG_OFFSET_CAPTURE) === 1;
    }

    /** @param array<int, array{0: string, 1: int}> $m */
    private function cut(string $text, array $m): string
    {
        if ($this->recording) {
            // Remember the winning phrase so annotate() can highlight it.
            $this->matchedPhrase = ['text' => $m[0][0], 'kind' => $this->currentKind];
        }

        return substr_replace($text, ' ', $m[0][1], strlen($m[0][0]));
    }

    private function num(string $token): int
    {
        $token = mb_strtolower(trim($token));

        return ctype_digit($token) ? (int) $token : (self::NUMBER_WORDS[$token] ?? 1);
    }

    private function daily(int $interval): string
    {
        return $interval <= 1 ? 'FREQ=DAILY' : "FREQ=DAILY;INTERVAL={$interval}";
    }

    private function weekly(int $interval): string
    {
        return $interval <= 1 ? 'FREQ=WEEKLY;BYDAY=MO' : "FREQ=WEEKLY;INTERVAL={$interval};BYDAY=MO";
    }

    private function monthly(int $interval): string
    {
        return $interval <= 1 ? 'FREQ=MONTHLY' : "FREQ=MONTHLY;INTERVAL={$interval}";
    }

    /**
     * @return array{rrule: string, anchor: CarbonImmutable}
     */
    private function rec(string $rrule, CarbonImmutable $anchor): array
    {
        return ['rrule' => $rrule, 'anchor' => $anchor];
    }

    // MARK: - Live preview annotation

    /**
     * A live "how will this be parsed" preview: the original sentence tiled into
     * coloured segments (date / recurrence / title / ignored), each carrying the
     * resolved value. Platform-agnostic — web, iOS and Mac render the same data.
     * Concatenating every segment's `text` reproduces the input verbatim, so
     * front ends never need to do offset math.
     *
     * @return array{
     *     input: string,
     *     title: string,
     *     date: ?array{iso: string, label: string},
     *     recurrence: ?array{rrule: string, summary: string, anchor_iso: string, anchor_label: string},
     *     segments: list<array{type: string, text: string, start: int, length: int, resolved?: string}>,
     * }
     */
    public function annotate(string $input, ?CarbonImmutable $today = null): array
    {
        $today = ($today ?? CarbonImmutable::today())->startOfDay();
        $original = $this->normalize($input);

        $this->recording = true;
        $this->matchedPhrase = null;
        try {
            $parsed = $this->parse($input, $today);
        } finally {
            $this->recording = false;
        }

        $date = $parsed['date'];
        $recurrence = $parsed['recurrence'];

        $dateInfo = $date !== null
            ? ['iso' => $date->toDateString(), 'label' => $this->dateLabel($date)]
            : null;

        $recurrenceInfo = null;
        $recurrenceResolved = null;
        if ($recurrence !== null) {
            $presets = $this->presets ?? app(RecurrencePresets::class);
            $summary = $presets->describe($recurrence['rrule'], $recurrence['anchor'])['label'];
            $anchorLabel = $this->shortLabel($recurrence['anchor']);
            $recurrenceInfo = [
                'rrule' => $recurrence['rrule'],
                'summary' => $summary,
                'anchor_iso' => $recurrence['anchor']->toDateString(),
                'anchor_label' => $anchorLabel,
            ];
            $recurrenceResolved = $summary.' · vanaf '.$anchorLabel;
        }

        $segments = $this->buildSegments(
            $original,
            $this->matchedPhrase,
            $parsed['title'],
            $dateInfo['label'] ?? null,
            $recurrenceResolved,
        );

        return [
            'input' => $original,
            'title' => $parsed['title'],
            'date' => $dateInfo,
            'recurrence' => $recurrenceInfo,
            'segments' => $segments,
        ];
    }

    /**
     * Tile the original sentence into typed segments. The date/recurrence phrase
     * is located verbatim; the cleaned title is matched word-for-word as an
     * ordered subsequence (so stripped filler shows as "ignored").
     *
     * @param  array{text: string, kind: string}|null  $phrase
     * @return list<array{type: string, text: string, start: int, length: int, resolved?: string}>
     */
    private function buildSegments(string $original, ?array $phrase, string $title, ?string $dateResolved, ?string $recurrenceResolved): array
    {
        $n = strlen($original);
        if ($n === 0) {
            return [];
        }

        $types = array_fill(0, $n, null);

        $pStart = $pEnd = -1;
        if ($phrase !== null && $phrase['text'] !== '') {
            $pos = strpos($original, $phrase['text']);
            if ($pos !== false) {
                $pStart = $pos;
                $pEnd = $pos + strlen($phrase['text']);
            }
        }

        $titleWords = preg_split('/\s+/u', $title, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $ti = 0;
        preg_match_all('/\S+/u', $original, $wm, PREG_OFFSET_CAPTURE);
        foreach ($wm[0] as [$word, $off]) {
            $start = $off;
            $end = $off + strlen($word);
            if ($pStart >= 0 && $start >= $pStart && $end <= $pEnd) {
                continue; // belongs to the date/recurrence phrase
            }
            $type = 'ignored';
            if ($ti < count($titleWords) && $this->wordCore($word) === $this->wordCore($titleWords[$ti])) {
                $type = 'title';
                $ti++;
            }
            for ($i = $start; $i < $end; $i++) {
                $types[$i] = $type;
            }
        }

        if ($pStart >= 0) {
            for ($i = $pStart; $i < $pEnd; $i++) {
                $types[$i] = $phrase['kind'];
            }
        }

        // Glue whitespace onto the preceding run so segments tile the input —
        // except the space *after* a date/recurrence phrase stays neutral, so the
        // highlight hugs the phrase tightly instead of trailing into the gap.
        $last = null;
        for ($i = 0; $i < $n; $i++) {
            if ($types[$i] === null) {
                $types[$i] = ($last === 'date' || $last === 'recurrence') ? 'ignored' : $last;
            } else {
                $last = $types[$i];
            }
        }

        $segments = [];
        $i = 0;
        while ($i < $n) {
            $type = $types[$i] ?? 'ignored';
            $j = $i;
            while ($j < $n && ($types[$j] ?? 'ignored') === $type) {
                $j++;
            }
            $segment = [
                'type' => $type,
                'text' => substr($original, $i, $j - $i),
                'start' => $i,
                'length' => $j - $i,
            ];
            if ($type === 'date' && $dateResolved !== null) {
                $segment['resolved'] = $dateResolved;
            }
            if ($type === 'recurrence' && $recurrenceResolved !== null) {
                $segment['resolved'] = $recurrenceResolved;
            }
            $segments[] = $segment;
            $i = $j;
        }

        return $segments;
    }

    /** Lowercased, edge-punctuation-stripped form for tolerant word matching. */
    private function wordCore(string $word): string
    {
        return (string) preg_replace('/^[^\p{L}\p{N}]+|[^\p{L}\p{N}]+$/u', '', mb_strtolower($word));
    }

    /** "dinsdag 26 mei" */
    private function dateLabel(CarbonImmutable $date): string
    {
        return $date->locale('nl')->isoFormat('dddd D MMMM');
    }

    /** "di 26 mei" */
    private function shortLabel(CarbonImmutable $date): string
    {
        return $date->locale('nl')->isoFormat('ddd D MMM');
    }
}

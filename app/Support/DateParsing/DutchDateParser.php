<?php

namespace App\Support\DateParsing;

use App\Support\DateParsing\Rules\Date\BareWeekdayRule;
use App\Support\DateParsing\Rules\Date\CalendarDateRule;
use App\Support\DateParsing\Rules\Date\MonthDayRule;
use App\Support\DateParsing\Rules\Date\NextWorkdayRule;
use App\Support\DateParsing\Rules\Date\OverDurationRule;
use App\Support\DateParsing\Rules\Date\QualifiedWeekdayRule;
use App\Support\DateParsing\Rules\Date\RelativeDayRule;
use App\Support\DateParsing\Rules\Date\RelativeWeekWeekdayRule;
use App\Support\DateParsing\Rules\Date\WeekBoundaryRule;
use App\Support\DateParsing\Rules\Date\WeekendRule;
use App\Support\DateParsing\Rules\Recurrence\DailyRule;
use App\Support\DateParsing\Rules\Recurrence\EveryDayExceptRule;
use App\Support\DateParsing\Rules\Recurrence\EveryWeekdayRule;
use App\Support\DateParsing\Rules\Recurrence\MonthlyRule;
use App\Support\DateParsing\Rules\Recurrence\NthWeekdayRule;
use App\Support\DateParsing\Rules\Recurrence\WeeklyRule;
use App\Support\DateParsing\Rules\Recurrence\WorkdayRule;
use App\Support\DateParsing\Rules\Recurrence\YearlyRule;
use App\Support\RecurrencePresets;
use Carbon\CarbonImmutable;

/**
 * Best-effort Dutch natural-language parser for the quick-add field. Pulls a
 * one-off date or a recurrence out of a free-text line and returns the leftover
 * as the todo title, stripping reminder/command filler ("kan je me",
 * "herinner mij aan", "maak een todo voor", …).
 *
 * Rule-based and ordered (specific → general): the parser runs an ordered list
 * of {@see Rule} objects and the first match wins. Each rule owns one case; the
 * ordered lists below are the single source of truth for precedence. To add or
 * tune phrasing, edit a focused rule under Rules/, not this class. The produced
 * RRULEs match App\Support\RecurrencePresets so the readable summary lights up.
 */
class DutchDateParser
{
    /** @var list<Rule> Tried first; a match short-circuits the one-off date rules. */
    private array $recurrenceRules;

    /** @var list<Rule> Tried only when no recurrence matched. */
    private array $dateRules;

    public function __construct(private ?RecurrencePresets $presets = null)
    {
        $this->recurrenceRules = [
            new WorkdayRule,
            new EveryDayExceptRule,
            new NthWeekdayRule,
            new EveryWeekdayRule,
            new DailyRule,
            new WeeklyRule,
            new MonthlyRule,
            new YearlyRule,
        ];

        $this->dateRules = [
            new RelativeWeekWeekdayRule,
            new OverDurationRule,
            new WeekBoundaryRule,
            new MonthDayRule,
            new NextWorkdayRule,
            new QualifiedWeekdayRule,
            new RelativeDayRule,
            new WeekendRule,
            new CalendarDateRule,
            new BareWeekdayRule,
        ];
    }

    /**
     * @return array{title: string, date: ?CarbonImmutable, recurrence: ?array{rrule: string, anchor: CarbonImmutable}}
     */
    public function parse(string $input, ?CarbonImmutable $today = null): array
    {
        $today = ($today ?? CarbonImmutable::today())->startOfDay();
        $result = $this->run($input, $today);

        return ['title' => $result['title'], 'date' => $result['date'], 'recurrence' => $result['recurrence']];
    }

    /**
     * A live "how will this be parsed" preview. See {@see Annotator} for the
     * segment contract. Platform-agnostic — web, iOS and Mac render the same data.
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
        $result = $this->run($input, $today);

        return (new Annotator($this->presets))->build(
            $original,
            $result['match'],
            $result['title'],
            $result['date'],
            $result['recurrence'],
        );
    }

    /**
     * The shared pipeline: strip filler, match recurrence-then-date rules, strip
     * the leftover connectors and clean up into a title. Returns the winning
     * match too, so annotate() can highlight the phrase without re-parsing.
     *
     * @return array{
     *     title: string,
     *     date: ?CarbonImmutable,
     *     recurrence: ?array{rrule: string, anchor: CarbonImmutable},
     *     match: ?RuleMatch,
     * }
     */
    private function run(string $input, CarbonImmutable $today): array
    {
        $text = $this->normalize($input);
        $isReminder = Filler::looksLikeReminder($text);
        $text = Filler::stripLeadingCommands($text);
        $text = Filler::stripTimes($text);

        $date = null;
        $recurrence = null;

        [$match, $text] = $this->apply($this->recurrenceRules, $text, $today);
        if ($match !== null) {
            $recurrence = ['rrule' => $match->rrule, 'anchor' => $match->anchor];
        } else {
            [$match, $text] = $this->apply($this->dateRules, $text, $today);
            $date = $match?->date;
        }

        if ($match !== null) {
            $text = Filler::stripLeadingCommands($text);
            $text = Filler::stripConnectors($text);
        }

        $title = Filler::cleanup($text);

        if ($title === '') {
            // A bare reminder ("herinner me dinsdag") leaves no task text once the
            // filler and date are stripped, but the scheduling intent is clear —
            // keep the date/recurrence under a generic title instead of dropping it.
            if ($isReminder && $match !== null) {
                return ['title' => 'Herinnering', 'date' => $date, 'recurrence' => $recurrence, 'match' => $match];
            }

            return ['title' => trim($input), 'date' => null, 'recurrence' => null, 'match' => null];
        }

        return ['title' => $title, 'date' => $date, 'recurrence' => $recurrence, 'match' => $match];
    }

    /**
     * Try each rule in order; on the first match, blank its phrase out of the
     * working text (so the title extraction never sees it) and return.
     *
     * @param  list<Rule>  $rules
     * @return array{0: ?RuleMatch, 1: string}
     */
    private function apply(array $rules, string $text, CarbonImmutable $today): array
    {
        foreach ($rules as $rule) {
            $match = $rule->match($text, $today);
            if ($match !== null) {
                $text = substr_replace($text, ' ', $match->start, $match->length);

                return [$match, $text];
            }
        }

        return [null, $text];
    }

    private function normalize(string $input): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $input));
    }
}

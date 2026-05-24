<?php

namespace App\Support\DateParsing\Rules\Date;

use App\Support\DateParsing\Clock;
use App\Support\DateParsing\Lexicon;
use App\Support\DateParsing\Rule;
use App\Support\DateParsing\RuleMatch;
use Carbon\CarbonImmutable;

/**
 * A weekday with a qualifier: "aanstaande vrijdag", "komende dinsdag", "volgende
 * maandag", "deze woensdag", or "op donderdag". "deze"/"volgende" pin the week;
 * the rest resolve to the next upcoming occurrence.
 */
class QualifiedWeekdayRule extends Rule
{
    public function match(string $text, CarbonImmutable $today): ?RuleMatch
    {
        $wd = Lexicon::WEEKDAY_RE;

        if ($this->find($text, '/\b(aanstaande|a\.?s\.?|komende|komend|volgende|deze)\s+('.$wd.')\b/iu', $m)) {
            $qualifier = mb_strtolower($m[1][0]);
            $iso = Lexicon::weekday($m[2][0]);
            $date = match (true) {
                $qualifier === 'deze' => Clock::weekOffsetWeekday($today, 0, $iso),
                $qualifier === 'volgende' => Clock::weekOffsetWeekday($today, 1, $iso),
                default => Clock::upcomingWeekday($today, $iso),
            };

            return RuleMatch::date($m, $date);
        }

        if ($this->find($text, '/\bop\s+('.$wd.')\b/iu', $m)) {
            return RuleMatch::date($m, Clock::upcomingWeekday($today, Lexicon::weekday($m[1][0])));
        }

        return null;
    }
}

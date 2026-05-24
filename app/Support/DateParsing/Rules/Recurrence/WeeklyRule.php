<?php

namespace App\Support\DateParsing\Rules\Recurrence;

use App\Support\DateParsing\Clock;
use App\Support\DateParsing\Lexicon;
use App\Support\DateParsing\Rrule;
use App\Support\DateParsing\Rule;
use App\Support\DateParsing\RuleMatch;
use Carbon\CarbonImmutable;

/**
 * "elke 2 weken", "om de week", "tweewekelijks", "elke week", "wekelijks",
 * "per week". Anchors on the upcoming Monday and defaults BYDAY=MO.
 */
class WeeklyRule extends Rule
{
    public function match(string $text, CarbonImmutable $today): ?RuleMatch
    {
        $num = Lexicon::NUMBER_RE;

        if ($this->find($text, '/\b(?:'.Lexicon::EVERY.'|om de)\s+('.$num.')\s+weken\b/iu', $m)) {
            return RuleMatch::recurrence($m, Rrule::weekly(Lexicon::num($m[1][0])), Clock::upcomingWeekday($today, 1));
        }
        if ($this->find($text, '/\bom\s+de\s+(?:andere\s+)?week\b/iu', $m) || $this->find($text, '/\btweewekelijks\b/iu', $m)) {
            return RuleMatch::recurrence($m, 'FREQ=WEEKLY;INTERVAL=2;BYDAY=MO', Clock::upcomingWeekday($today, 1));
        }
        if ($this->find($text, '/\b'.Lexicon::EVERY.'\s+week\b/iu', $m) || $this->find($text, '/\bwekelijks\b/iu', $m) || $this->find($text, '/\bper\s+week\b/iu', $m)) {
            return RuleMatch::recurrence($m, 'FREQ=WEEKLY;BYDAY=MO', Clock::upcomingWeekday($today, 1));
        }

        return null;
    }
}

<?php

namespace App\Support\DateParsing\Rules\Date;

use App\Support\DateParsing\Clock;
use App\Support\DateParsing\Lexicon;
use App\Support\DateParsing\Rule;
use App\Support\DateParsing\RuleMatch;
use Carbon\CarbonImmutable;

/**
 * A weekday pinned to a relative week: "volgende week vrijdag",
 * "donderdag komende week", "deze week donderdag", "over 2 weken vrijdag".
 * Runs before OverDurationRule so the weekday is never dropped.
 */
class RelativeWeekWeekdayRule extends Rule
{
    public function match(string $text, CarbonImmutable $today): ?RuleMatch
    {
        $wd = Lexicon::WEEKDAY_RE;
        $num = Lexicon::NUMBER_RE;

        if ($this->find($text, '/\b(?:volgende|komende)\s+week\s+('.$wd.')\b/iu', $m)
            || $this->find($text, '/\b('.$wd.')\s+(?:volgende|komende)\s+week\b/iu', $m)) {
            return RuleMatch::date($m, Clock::weekOffsetWeekday($today, 1, Lexicon::weekday($m[1][0])));
        }

        if ($this->find($text, '/\bdeze\s+week\s+('.$wd.')\b/iu', $m)) {
            return RuleMatch::date($m, Clock::weekOffsetWeekday($today, 0, Lexicon::weekday($m[1][0])));
        }

        // "over N weken vrijdag" → that weekday in the target week.
        if ($this->find($text, '/\b(?:over|binnen)\s+('.$num.')\s+(?:weken|week|weekje)\s+(?:op\s+)?('.$wd.')\b/iu', $m)) {
            return RuleMatch::date($m, Clock::weekOffsetWeekday($today, Lexicon::num($m[1][0]), Lexicon::weekday($m[2][0])));
        }

        return null;
    }
}

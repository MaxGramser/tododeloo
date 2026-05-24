<?php

namespace App\Support\DateParsing\Rules\Date;

use App\Support\DateParsing\Clock;
use App\Support\DateParsing\Lexicon;
use App\Support\DateParsing\Rule;
use App\Support\DateParsing\RuleMatch;
use Carbon\CarbonImmutable;

/**
 * "na het weekend" → the coming Monday; "na vrijdag" → the day after that
 * weekday's next occurrence.
 */
class AfterRule extends Rule
{
    public function match(string $text, CarbonImmutable $today): ?RuleMatch
    {
        if ($this->find($text, '/\bna\s+het\s+weekend\b/iu', $m)) {
            return RuleMatch::date($m, Clock::upcomingWeekday($today, 1));
        }

        if ($this->find($text, '/\bna\s+('.Lexicon::WEEKDAY_RE.')\b/iu', $m)) {
            return RuleMatch::date($m, Clock::upcomingWeekday($today, Lexicon::weekday($m[1][0]))->addDay());
        }

        return null;
    }
}

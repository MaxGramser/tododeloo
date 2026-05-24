<?php

namespace App\Support\DateParsing\Rules\Date;

use App\Support\DateParsing\Clock;
use App\Support\DateParsing\Rule;
use App\Support\DateParsing\RuleMatch;
use Carbon\CarbonImmutable;

/**
 * "dit weekend" / "komend weekend" / "over het weekend" → the upcoming Saturday;
 * "volgend weekend" → the Saturday after that.
 */
class WeekendRule extends Rule
{
    public function match(string $text, CarbonImmutable $today): ?RuleMatch
    {
        if ($this->find($text, '/\b(?:volgend|volgende)\s+weekend\b/iu', $m)) {
            return RuleMatch::date($m, Clock::upcomingWeekday($today, 6)->addWeek());
        }

        if ($this->find($text, '/\b(?:dit|komend|komende|aankomend)\s+weekend\b/iu', $m)
            || $this->find($text, '/\b(?:in|over)\s+het\s+weekend\b/iu', $m)) {
            return RuleMatch::date($m, Clock::upcomingWeekday($today, 6));
        }

        return null;
    }
}

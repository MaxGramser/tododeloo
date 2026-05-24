<?php

namespace App\Support\DateParsing\Rules\Date;

use App\Support\DateParsing\Clock;
use App\Support\DateParsing\Lexicon;
use App\Support\DateParsing\Rule;
use App\Support\DateParsing\RuleMatch;
use Carbon\CarbonImmutable;

/** The last resort: a weekday on its own → its next upcoming occurrence. */
class BareWeekdayRule extends Rule
{
    public function match(string $text, CarbonImmutable $today): ?RuleMatch
    {
        if ($this->find($text, '/\b('.Lexicon::WEEKDAY_RE.')\b/iu', $m)) {
            return RuleMatch::date($m, Clock::upcomingWeekday($today, Lexicon::weekday($m[1][0])));
        }

        return null;
    }
}

<?php

namespace App\Support\DateParsing\Rules\Recurrence;

use App\Support\DateParsing\Clock;
use App\Support\DateParsing\Lexicon;
use App\Support\DateParsing\Rrule;
use App\Support\DateParsing\Rule;
use App\Support\DateParsing\RuleMatch;
use Carbon\CarbonImmutable;

/** "elke dinsdag", "iedere vrijdag", "elke maandag en woensdag" → weekly on those weekdays. */
class EveryWeekdayRule extends Rule
{
    public function match(string $text, CarbonImmutable $today): ?RuleMatch
    {
        if ($this->find($text, '/\b'.Lexicon::EVERY.'\s+'.Lexicon::weekdayList().'\b/iu', $m)) {
            $isoDays = [];
            if (preg_match_all('/'.Lexicon::WEEKDAY_RE.'/iu', $m[1][0], $mm)) {
                foreach ($mm[0] as $name) {
                    $isoDays[] = Lexicon::weekday($name);
                }
            }
            $isoDays = array_values(array_unique($isoDays));
            sort($isoDays);

            return RuleMatch::recurrence($m, 'FREQ=WEEKLY;BYDAY='.Rrule::dayCodes($isoDays), Clock::nextDayIn($today, $isoDays));
        }

        return null;
    }
}

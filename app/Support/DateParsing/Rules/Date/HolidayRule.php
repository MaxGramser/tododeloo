<?php

namespace App\Support\DateParsing\Rules\Date;

use App\Support\DateParsing\Holidays;
use App\Support\DateParsing\Rule;
use App\Support\DateParsing\RuleMatch;
use Carbon\CarbonImmutable;

/**
 * Dutch holidays — "kerst", "met pasen", "op koningsdag", "tweede paasdag",
 * "hemelvaart". Runs first so "goede vrijdag" resolves to the holiday rather
 * than a bare weekday.
 */
class HolidayRule extends Rule
{
    public function match(string $text, CarbonImmutable $today): ?RuleMatch
    {
        $names = implode('|', array_map(
            fn (string $k): string => str_replace(' ', '\s+', preg_quote($k, '/')),
            Holidays::keywords(),
        ));

        if ($this->find($text, '/\b(?:(?:met|op|rond|tijdens|voor)\s+)?('.$names.')\b/iu', $m)) {
            $date = Holidays::resolve($m[1][0], $today);
            if ($date !== null) {
                return RuleMatch::date($m, $date);
            }
        }

        return null;
    }
}

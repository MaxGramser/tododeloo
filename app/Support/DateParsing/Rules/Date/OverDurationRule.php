<?php

namespace App\Support\DateParsing\Rules\Date;

use App\Support\DateParsing\Lexicon;
use App\Support\DateParsing\Rule;
use App\Support\DateParsing\RuleMatch;
use Carbon\CarbonImmutable;

/**
 * A span from today: "over 3 dagen", "binnen 2 weken", "over een maand",
 * "over 5 jaar". "over" and "binnen" are treated alike.
 */
class OverDurationRule extends Rule
{
    public function match(string $text, CarbonImmutable $today): ?RuleMatch
    {
        $num = Lexicon::NUMBER_RE;
        $in = '(?:over|binnen)';

        if ($this->find($text, '/\b'.$in.'\s+('.$num.')\s+dag(?:en|je)?\b/iu', $m)) {
            return RuleMatch::date($m, $today->addDays(Lexicon::num($m[1][0])));
        }
        if ($this->find($text, '/\b'.$in.'\s+('.$num.')\s+(?:weken|week|weekje)\b/iu', $m)) {
            return RuleMatch::date($m, $today->addWeeks(Lexicon::num($m[1][0])));
        }
        if ($this->find($text, '/\b'.$in.'\s+('.$num.')\s+maand(?:en)?\b/iu', $m)) {
            return RuleMatch::date($m, $today->addMonths(Lexicon::num($m[1][0])));
        }
        if ($this->find($text, '/\b'.$in.'\s+('.$num.')\s+ja(?:ar|ren)\b/iu', $m)) {
            return RuleMatch::date($m, $today->addYears(Lexicon::num($m[1][0])));
        }

        return null;
    }
}

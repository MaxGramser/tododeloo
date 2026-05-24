<?php

namespace App\Support\DateParsing\Rules\Date;

use App\Support\DateParsing\Clock;
use App\Support\DateParsing\Lexicon;
use App\Support\DateParsing\Rule;
use App\Support\DateParsing\RuleMatch;
use Carbon\CarbonImmutable;

/**
 * The start/middle/end of a named month or of the current month/year:
 * "begin juni", "half juli", "eind augustus", "eind van de maand", "deze maand",
 * "begin van het jaar".
 */
class MonthPartRule extends Rule
{
    public function match(string $text, CarbonImmutable $today): ?RuleMatch
    {
        $month = Lexicon::monthRe();

        if ($this->find($text, '/\b(begin|half|halverwege|midden|eind|einde)\s+(?:van\s+)?('.$month.')\b/iu', $m)) {
            return RuleMatch::date($m, Clock::monthPart($today, Lexicon::month($m[2][0]), $this->part($m[1][0])));
        }

        if ($this->find($text, '/\b(begin|eind|einde)\s+(?:van\s+)?(?:de\s+|deze\s+)?maand\b/iu', $m)) {
            return RuleMatch::date($m, $this->part($m[1][0]) === 'begin' ? Clock::beginOfMonth($today) : Clock::endOfMonth($today));
        }
        if ($this->find($text, '/\bdeze\s+maand\b/iu', $m)) {
            return RuleMatch::date($m, Clock::endOfMonth($today));
        }

        if ($this->find($text, '/\b(begin|eind|einde)\s+(?:van\s+)?(?:het\s+|dit\s+)?jaar\b/iu', $m)) {
            return RuleMatch::date($m, $this->part($m[1][0]) === 'begin' ? Clock::beginOfYear($today) : Clock::endOfYear($today));
        }
        if ($this->find($text, '/\bdit\s+jaar\b/iu', $m)) {
            return RuleMatch::date($m, Clock::endOfYear($today));
        }

        return null;
    }

    private function part(string $token): string
    {
        return match (mb_strtolower($token)) {
            'begin' => 'begin',
            'half', 'halverwege', 'midden' => 'half',
            default => 'eind',
        };
    }
}

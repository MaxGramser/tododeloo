<?php

namespace App\Support\DateParsing;

/**
 * The Dutch vocabulary every rule shares: weekday / month / number words and the
 * regex fragments built from them. Rules compose these into their own patterns,
 * so language-specific spelling lives here once and a new rule never re-declares
 * it.
 */
final class Lexicon
{
    /** Full Dutch weekday names → ISO weekday (Mon=1 … Sun=7). */
    public const WEEKDAYS = [
        'maandag' => 1, 'dinsdag' => 2, 'woensdag' => 3, 'donderdag' => 4,
        'vrijdag' => 5, 'zaterdag' => 6, 'zondag' => 7,
    ];

    public const DAY_CODE = [1 => 'MO', 2 => 'TU', 3 => 'WE', 4 => 'TH', 5 => 'FR', 6 => 'SA', 7 => 'SU'];

    public const MONTHS = [
        'januari' => 1, 'februari' => 2, 'maart' => 3, 'april' => 4, 'mei' => 5, 'juni' => 6,
        'juli' => 7, 'augustus' => 8, 'september' => 9, 'oktober' => 10, 'november' => 11, 'december' => 12,
        'jan' => 1, 'feb' => 2, 'mrt' => 3, 'apr' => 4, 'jun' => 6, 'jul' => 7, 'aug' => 8,
        'sep' => 9, 'sept' => 9, 'okt' => 10, 'nov' => 11, 'dec' => 12,
    ];

    public const NUMBER_WORDS = [
        'een' => 1, 'één' => 1, 'twee' => 2, 'drie' => 3, 'vier' => 4, 'vijf' => 5,
        'zes' => 6, 'zeven' => 7, 'acht' => 8, 'negen' => 9, 'tien' => 10,
        'elf' => 11, 'twaalf' => 12, 'paar' => 2,
    ];

    /** Regex fragment matching any weekday name. */
    public const WEEKDAY_RE = 'maandag|dinsdag|woensdag|donderdag|vrijdag|zaterdag|zondag';

    /** Regex fragment matching a count written as digits or a Dutch number word. */
    public const NUMBER_RE = '\d+|een|één|twee|drie|vier|vijf|zes|zeven|acht|negen|tien|elf|twaalf|paar';

    /** Regex fragment matching an "every" lead-in for a recurrence. */
    public const EVERY = '(?:elke|iedere|elk|ieder|alle)';

    /** Regex fragment matching an "except" lead-in inside a recurrence. */
    public const EXCEPT = '(?:behalve|niet\s+op|met\s+uitzondering\s+van)';

    /** Month-name alternation, longest spelling first so full names win over abbreviations. */
    public static function monthRe(): string
    {
        return implode('|', array_keys(self::MONTHS));
    }

    /** A capturing fragment for one or more weekdays joined by "," or "en". */
    public static function weekdayList(): string
    {
        return '((?:'.self::WEEKDAY_RE.')(?:(?:\s*,\s*|\s+en\s+)(?:'.self::WEEKDAY_RE.'))*)';
    }

    /** ISO weekday for a Dutch weekday name. */
    public static function weekday(string $name): int
    {
        return self::WEEKDAYS[mb_strtolower($name)];
    }

    /** Month number for a Dutch month name or abbreviation. */
    public static function month(string $name): int
    {
        return self::MONTHS[mb_strtolower($name)];
    }

    /** A count token ("3", "drie", "paar") as an int; unknown words → 1. */
    public static function num(string $token): int
    {
        $token = mb_strtolower(trim($token));

        return ctype_digit($token) ? (int) $token : (self::NUMBER_WORDS[$token] ?? 1);
    }

    /** "eerste" → 1, "laatste" → -1, "2e" → 2, … */
    public static function ordinal(string $token): int
    {
        $token = mb_strtolower(trim($token));

        return match (true) {
            str_starts_with($token, 'eerste') => 1,
            str_starts_with($token, 'tweede') => 2,
            str_starts_with($token, 'derde') => 3,
            str_starts_with($token, 'vierde') => 4,
            str_starts_with($token, 'vijfde') => 5,
            str_starts_with($token, 'laatste') => -1,
            default => max(1, (int) preg_replace('/\D/', '', $token)),
        };
    }
}

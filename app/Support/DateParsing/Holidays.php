<?php

namespace App\Support\DateParsing;

use Carbon\CarbonImmutable;

/**
 * Resolves Dutch holiday names to a concrete date, this year or next if it
 * already passed. Easter and the feasts that hang off it are computed with the
 * Anonymous Gregorian algorithm, so no `ext-calendar` is required.
 */
final class Holidays
{
    /**
     * Every recognised spelling, longest first so the rule's regex prefers the
     * most specific name ("eerste kerstdag" over "kerst").
     *
     * @return list<string>
     */
    public static function keywords(): array
    {
        $keys = [
            'nieuwjaarsdag', 'nieuwjaar', 'koningsdag', 'bevrijdingsdag',
            'sinterklaas', 'pakjesavond', 'kerstavond', 'eerste kerstdag',
            'tweede kerstdag', 'kerstmis', 'kerst', 'oudejaarsavond',
            'oudejaarsdag', 'oudjaar', 'oud en nieuw', 'goede vrijdag',
            'eerste paasdag', 'tweede paasdag', 'paaszondag', 'paasmaandag',
            'pasen', 'hemelvaartsdag', 'hemelvaart', 'eerste pinksterdag',
            'tweede pinksterdag', 'pinksteren',
        ];
        usort($keys, fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        return $keys;
    }

    public static function resolve(string $name, CarbonImmutable $today): ?CarbonImmutable
    {
        $name = trim(mb_strtolower((string) preg_replace('/\s+/u', ' ', $name)));

        $build = fn (int $year): ?CarbonImmutable => match ($name) {
            'nieuwjaar', 'nieuwjaarsdag' => self::fixed($today, $year, 1, 1),
            'koningsdag' => self::fixed($today, $year, 4, 27),
            'bevrijdingsdag' => self::fixed($today, $year, 5, 5),
            'sinterklaas', 'pakjesavond' => self::fixed($today, $year, 12, 5),
            'kerstavond' => self::fixed($today, $year, 12, 24),
            'kerst', 'kerstmis', 'eerste kerstdag' => self::fixed($today, $year, 12, 25),
            'tweede kerstdag' => self::fixed($today, $year, 12, 26),
            'oudjaar', 'oudejaarsavond', 'oudejaarsdag', 'oud en nieuw' => self::fixed($today, $year, 12, 31),
            'goede vrijdag' => self::easter($today, $year)->subDays(2),
            'pasen', 'eerste paasdag', 'paaszondag' => self::easter($today, $year),
            'tweede paasdag', 'paasmaandag' => self::easter($today, $year)->addDay(),
            'hemelvaart', 'hemelvaartsdag' => self::easter($today, $year)->addDays(39),
            'pinksteren', 'eerste pinksterdag' => self::easter($today, $year)->addDays(49),
            'tweede pinksterdag' => self::easter($today, $year)->addDays(50),
            default => null,
        };

        $date = $build($today->year);
        if ($date === null) {
            return null;
        }

        return $date->lt($today) ? $build($today->year + 1) : $date;
    }

    private static function fixed(CarbonImmutable $today, int $year, int $month, int $day): CarbonImmutable
    {
        return CarbonImmutable::create($year, $month, $day, 0, 0, 0, $today->timezone);
    }

    /** Western (Gregorian) Easter Sunday — Anonymous algorithm, no extension needed. */
    private static function easter(CarbonImmutable $today, int $year): CarbonImmutable
    {
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day = (($h + $l - 7 * $m + 114) % 31) + 1;

        return self::fixed($today, $year, $month, $day);
    }
}

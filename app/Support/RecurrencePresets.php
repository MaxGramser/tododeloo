<?php

namespace App\Support;

use App\Enums\RecurrencePreset;
use Carbon\CarbonInterface;

/**
 * Single source of truth for turning a friendly preset + anchor date into an
 * RFC 5545 RRULE string and a Dutch label. The web and iOS clients send a
 * preset key (or a raw rrule for the custom builder); the rule itself is
 * always derived here so the three surfaces never drift.
 */
class RecurrencePresets
{
    /** RRULE weekday codes, indexed by ISO weekday (Mon=1) minus one. */
    private const RRULE_DAY = ['MO', 'TU', 'WE', 'TH', 'FR', 'SA', 'SU'];

    private const DAY_LABEL = ['maandag', 'dinsdag', 'woensdag', 'donderdag', 'vrijdag', 'zaterdag', 'zondag'];

    private const ORDINAL = [1 => '1e', 2 => '2e', 3 => '3e', 4 => '4e'];

    public function rrule(RecurrencePreset $preset, CarbonInterface $anchor): string
    {
        return match ($preset) {
            RecurrencePreset::Daily => 'FREQ=DAILY',
            RecurrencePreset::Weekdays => 'FREQ=WEEKLY;BYDAY=MO,TU,WE,TH,FR',
            RecurrencePreset::Weekly => 'FREQ=WEEKLY;BYDAY='.$this->dayCode($anchor),
            RecurrencePreset::MonthlyNthWeekday => 'FREQ=MONTHLY;BYDAY='.$this->nth($anchor).$this->dayCode($anchor),
            RecurrencePreset::HalfYearly => 'FREQ=MONTHLY;INTERVAL=6',
            RecurrencePreset::Yearly => 'FREQ=YEARLY',
        };
    }

    public function label(RecurrencePreset $preset, CarbonInterface $anchor): string
    {
        return match ($preset) {
            RecurrencePreset::Daily => 'Elke dag',
            RecurrencePreset::Weekdays => 'Elke werkdag',
            RecurrencePreset::Weekly => 'Elke '.$this->dayLabel($anchor),
            RecurrencePreset::MonthlyNthWeekday => 'Maandelijks op de '.$this->ordinalLabel($anchor).' '.$this->dayLabel($anchor),
            RecurrencePreset::HalfYearly => 'Elk half jaar',
            RecurrencePreset::Yearly => 'Elk jaar',
        };
    }

    /**
     * All presets resolved against an anchor date, for rendering a picker.
     *
     * @return list<array{key: string, label: string, rrule: string}>
     */
    public function options(CarbonInterface $anchor): array
    {
        return array_map(fn (RecurrencePreset $preset): array => [
            'key' => $preset->value,
            'label' => $this->label($preset, $anchor),
            'rrule' => $this->rrule($preset, $anchor),
        ], RecurrencePreset::cases());
    }

    /**
     * Describe a stored RRULE in Dutch. When the rule matches one of the presets
     * (resolved against its anchor) the preset key is returned too, so clients can
     * tick the active option. Custom rules fall back to a generated description.
     *
     * @return array{preset: ?string, label: string}
     */
    public function describe(string $rrule, CarbonInterface $anchor): array
    {
        foreach (RecurrencePreset::cases() as $preset) {
            if ($this->rrule($preset, $anchor) === $rrule) {
                return ['preset' => $preset->value, 'label' => $this->label($preset, $anchor)];
            }
        }

        return ['preset' => null, 'label' => $this->describeCustom($rrule)];
    }

    /** Build a Dutch sentence for an arbitrary RRULE (the custom builder's output). */
    private function describeCustom(string $rrule): string
    {
        $parts = [];

        foreach (explode(';', $rrule) as $segment) {
            [$key, $value] = array_pad(explode('=', $segment, 2), 2, '');
            $parts[strtoupper($key)] = strtoupper($value);
        }

        $interval = isset($parts['INTERVAL']) ? max(1, (int) $parts['INTERVAL']) : 1;

        return match ($parts['FREQ'] ?? '') {
            'DAILY' => $interval === 1 ? 'Elke dag' : "Elke {$interval} dagen",
            'WEEKLY' => $this->describeWeekly($interval, $parts['BYDAY'] ?? null),
            'MONTHLY' => $this->describeMonthly($interval, $parts['BYDAY'] ?? null, $parts['BYMONTHDAY'] ?? null, $parts['BYSETPOS'] ?? null),
            'YEARLY' => $interval === 1 ? 'Elk jaar' : "Elke {$interval} jaar",
            default => 'Herhaalt',
        };
    }

    private function describeWeekly(int $interval, ?string $byDay): string
    {
        if ($byDay === null || $byDay === '') {
            return $interval === 1 ? 'Elke week' : "Elke {$interval} weken";
        }

        $codes = explode(',', $byDay);

        if ($interval === 1 && $codes === ['MO', 'TU', 'WE', 'TH', 'FR']) {
            return 'Elke werkdag';
        }

        $days = $this->joinDutch(array_map(fn (string $code): string => $this->labelForCode($code), $codes));

        return $interval === 1 ? "Elke {$days}" : "Elke {$interval} weken op {$days}";
    }

    private function describeMonthly(int $interval, ?string $byDay, ?string $byMonthDay, ?string $bySetPos = null): string
    {
        // The first/last workday of the month: BYDAY=MO..FR + BYSETPOS=±1.
        if ($bySetPos !== null && $bySetPos !== '' && $byDay === 'MO,TU,WE,TH,FR') {
            $where = 'op de '.((int) $bySetPos < 0 ? 'laatste' : 'eerste').' werkdag';

            return $interval === 1 ? "Maandelijks {$where}" : "Elke {$interval} maanden {$where}";
        }

        if ($byDay !== null && $byDay !== '' && preg_match('/^(-?\d+)([A-Z]{2})$/', $byDay, $m)) {
            $where = 'op de '.$this->ordinalFor((int) $m[1]).' '.$this->labelForCode($m[2]);

            return $interval === 1 ? "Maandelijks {$where}" : "Elke {$interval} maanden {$where}";
        }

        if ($byMonthDay !== null && $byMonthDay !== '') {
            $where = $byMonthDay === '-1' ? 'op de laatste dag' : "op de {$byMonthDay}e";

            return $interval === 1 ? "Maandelijks {$where}" : "Elke {$interval} maanden {$where}";
        }

        return match (true) {
            $interval === 1 => 'Elke maand',
            $interval === 6 => 'Elk half jaar',
            default => "Elke {$interval} maanden",
        };
    }

    private function labelForCode(string $code): string
    {
        $index = array_search($code, self::RRULE_DAY, true);

        return $index === false ? strtolower($code) : self::DAY_LABEL[$index];
    }

    private function ordinalFor(int $n): string
    {
        return $n < 0 ? 'laatste' : (self::ORDINAL[$n] ?? "{$n}e");
    }

    /** "maandag", "maandag en donderdag", "maandag, woensdag en vrijdag". */
    private function joinDutch(array $items): string
    {
        if (count($items) <= 1) {
            return implode('', $items);
        }

        $last = array_pop($items);

        return implode(', ', $items).' en '.$last;
    }

    private function dayCode(CarbonInterface $anchor): string
    {
        return self::RRULE_DAY[$anchor->dayOfWeekIso - 1];
    }

    private function dayLabel(CarbonInterface $anchor): string
    {
        return self::DAY_LABEL[$anchor->dayOfWeekIso - 1];
    }

    /** Which occurrence of the weekday this date is within its month (5th → last). */
    private function nth(CarbonInterface $anchor): string
    {
        $n = (int) ceil($anchor->day / 7);

        return $n >= 5 ? '-1' : (string) $n;
    }

    private function ordinalLabel(CarbonInterface $anchor): string
    {
        $n = (int) ceil($anchor->day / 7);

        return $n >= 5 ? 'laatste' : self::ORDINAL[$n];
    }
}

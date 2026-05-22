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

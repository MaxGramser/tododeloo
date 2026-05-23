<?php

namespace App\Support;

use App\Models\Todo;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Turns the outcome of a quick-add into a short Dutch confirmation so every
 * client (web toast, iOS/Mac toast, Siri) shows the same wording for what the
 * parser actually did. The message quotes the cleaned todo title; the
 * description says where it landed ("ingepland voor volgende week dinsdag",
 * "herhaalt elke dag").
 */
class QuickAddFeedback
{
    /** ISO weekday (Mon=1) → Dutch name. */
    private const WEEKDAYS = [
        1 => 'maandag', 2 => 'dinsdag', 3 => 'woensdag', 4 => 'donderdag',
        5 => 'vrijdag', 6 => 'zaterdag', 7 => 'zondag',
    ];

    private const MONTHS = [
        1 => 'januari', 2 => 'februari', 3 => 'maart', 4 => 'april', 5 => 'mei', 6 => 'juni',
        7 => 'juli', 8 => 'augustus', 9 => 'september', 10 => 'oktober', 11 => 'november', 12 => 'december',
    ];

    /**
     * @return array{message: string, description: string}
     */
    public function build(Todo $todo, ?CarbonImmutable $targetDate, ?CarbonImmutable $today = null): array
    {
        $today = ($today ?? CarbonImmutable::today())->startOfDay();
        $todo->loadMissing('recurrence');

        $description = match (true) {
            $todo->recurrence !== null => 'herhaalt '.lcfirst($todo->recurrence->describe()['label']),
            $targetDate !== null => 'ingepland voor '.$this->relativeDate($targetDate->startOfDay(), $today),
            default => 'toegevoegd',
        };

        return [
            'message' => '"'.$todo->title.'"',
            'description' => $description,
        ];
    }

    /** A friendly Dutch phrasing of a date relative to today. */
    private function relativeDate(CarbonImmutable $date, CarbonImmutable $today): string
    {
        return match (true) {
            $date->isSameDay($today) => 'vandaag',
            $date->isSameDay($today->addDay()) => 'morgen',
            $date->isSameDay($today->addDays(2)) => 'overmorgen',
            $date->isSameDay($today->subDay()) => 'gisteren',
            $date->isSameDay($today->subDays(2)) => 'eergisteren',
            default => $this->weekAware($date, $today),
        };
    }

    /** "donderdag" this week, "volgende week dinsdag" next week, else "26 mei". */
    private function weekAware(CarbonImmutable $date, CarbonImmutable $today): string
    {
        $thisWeek = $today->startOfWeek(CarbonInterface::MONDAY);
        $nextWeek = $thisWeek->addWeek();
        $weekAfter = $nextWeek->addWeek();
        $weekday = self::WEEKDAYS[$date->dayOfWeekIso];

        if ($date->gte($thisWeek) && $date->lt($nextWeek)) {
            return $weekday;
        }

        if ($date->gte($nextWeek) && $date->lt($weekAfter)) {
            return 'volgende week '.$weekday;
        }

        $label = $date->day.' '.self::MONTHS[$date->month];

        return $date->year === $today->year ? $label : $label.' '.$date->year;
    }
}

<?php

namespace App\Enums;

enum RecurrencePreset: string
{
    case Daily = 'daily';
    case Weekdays = 'weekdays';
    case Weekly = 'weekly';
    case MonthlyNthWeekday = 'monthly_nth_weekday';
    case HalfYearly = 'half_yearly';
    case Yearly = 'yearly';
}

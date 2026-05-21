<?php

namespace App\Enums;

enum Priority: string
{
    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';

    public function weight(): int
    {
        return match ($this) {
            self::High => 0,
            self::Normal => 1,
            self::Low => 2,
        };
    }
}

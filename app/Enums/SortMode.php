<?php

namespace App\Enums;

enum SortMode: string
{
    case Manual = 'manual';
    case CreatedAt = 'created_at';
    case Alphabetical = 'alphabetical';
    case Priority = 'priority';
}

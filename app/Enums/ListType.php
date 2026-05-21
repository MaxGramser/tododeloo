<?php

namespace App\Enums;

enum ListType: string
{
    case Master = 'master';
    case Daily = 'daily';
    case Custom = 'custom';
}

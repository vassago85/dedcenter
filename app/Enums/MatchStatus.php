<?php

namespace App\Enums;

enum MatchStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Completed = 'completed';
}

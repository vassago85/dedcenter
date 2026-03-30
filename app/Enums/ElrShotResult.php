<?php

namespace App\Enums;

enum ElrShotResult: string
{
    case Hit = 'hit';
    case Miss = 'miss';
    case NotTaken = 'not_taken';
}

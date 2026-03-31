<?php

namespace App\Enums;

enum PrsShotResult: string
{
    case Hit = 'hit';
    case Miss = 'miss';
    case NotTaken = 'not_taken';
}

<?php

namespace App\Enums;

enum SponsorScope: string
{
    case Platform = 'platform';
    case Match = 'match';
    case Matchbook = 'matchbook';
}

<?php

namespace App\Enums;

enum Province: string
{
    case EasternCape = 'eastern_cape';
    case FreeState = 'free_state';
    case Gauteng = 'gauteng';
    case KwaZuluNatal = 'kwazulu_natal';
    case Limpopo = 'limpopo';
    case Mpumalanga = 'mpumalanga';
    case NorthWest = 'north_west';
    case NorthernCape = 'northern_cape';
    case WesternCape = 'western_cape';

    public function label(): string
    {
        return match ($this) {
            self::EasternCape => 'Eastern Cape',
            self::FreeState => 'Free State',
            self::Gauteng => 'Gauteng',
            self::KwaZuluNatal => 'KwaZulu-Natal',
            self::Limpopo => 'Limpopo',
            self::Mpumalanga => 'Mpumalanga',
            self::NorthWest => 'North West',
            self::NorthernCape => 'Northern Cape',
            self::WesternCape => 'Western Cape',
        };
    }
}

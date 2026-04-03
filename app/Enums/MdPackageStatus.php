<?php

namespace App\Enums;

enum MdPackageStatus: string
{
    case Pending = 'pending';
    case Taken = 'taken';
    case Declined = 'declined';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Taken => 'Taken',
            self::Declined => 'Declined',
            self::Expired => 'Expired',
        };
    }
}

<?php

namespace App\Enums;

enum AdvertisingMode: string
{
    case MdReservedWindow = 'md_reserved_window';
    case PublicOpen = 'public_open';
    case SelfManaged = 'self_managed';

    public function label(): string
    {
        return match ($this) {
            self::MdReservedWindow => 'MD First Option',
            self::PublicOpen => 'Public Open',
            self::SelfManaged => 'MD Managed',
        };
    }
}

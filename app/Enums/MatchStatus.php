<?php

namespace App\Enums;

enum MatchStatus: string
{
    case Draft = 'draft';
    case PreRegistration = 'pre_registration';
    case RegistrationOpen = 'registration_open';
    case RegistrationClosed = 'registration_closed';
    case SquaddingOpen = 'squadding_open';
    case Active = 'active';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::PreRegistration => 'Pre-Registration',
            self::RegistrationOpen => 'Registration Open',
            self::RegistrationClosed => 'Registration Closed',
            self::SquaddingOpen => 'Squadding Open',
            self::Active => 'Active',
            self::Completed => 'Completed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'slate',
            self::PreRegistration => 'violet',
            self::RegistrationOpen => 'sky',
            self::RegistrationClosed => 'amber',
            self::SquaddingOpen => 'indigo',
            self::Active => 'green',
            self::Completed => 'zinc',
        };
    }

    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::PreRegistration, self::RegistrationOpen, self::Active],
            self::PreRegistration => [self::RegistrationOpen, self::RegistrationClosed],
            self::RegistrationOpen => [self::RegistrationClosed],
            self::RegistrationClosed => [self::SquaddingOpen, self::Active],
            self::SquaddingOpen => [self::Active],
            self::Active => [self::Completed],
            self::Completed => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions());
    }

    public function ordinal(): int
    {
        return match ($this) {
            self::Draft => 0,
            self::PreRegistration => 1,
            self::RegistrationOpen => 2,
            self::RegistrationClosed => 3,
            self::SquaddingOpen => 4,
            self::Active => 5,
            self::Completed => 6,
        };
    }
}

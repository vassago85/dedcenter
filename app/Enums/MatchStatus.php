<?php

namespace App\Enums;

enum MatchStatus: string
{
    case Draft = 'draft';
    case PreRegistration = 'pre_registration';
    case RegistrationOpen = 'registration_open';
    case RegistrationClosed = 'registration_closed';
    case SquaddingOpen = 'squadding_open';
    case SquaddingClosed = 'squadding_closed';
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
            self::SquaddingClosed => 'Squadding Closed',
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
            self::SquaddingClosed => 'cyan',
            self::Active => 'green',
            self::Completed => 'zinc',
        };
    }

    public function allowedTransitions(): array
    {
        // Match directors sometimes need to walk backwards through the lifecycle
        // (e.g. re-open squadding after a late arrival, go back to
        // RegistrationOpen because a scheduled match got postponed, or
        // un-complete a match that was finalised by accident). Achievements
        // and notifications fired on the way into Completed are not rolled
        // back on the way out — the MD just gets the match back into Active
        // so they can keep editing. Re-Completing does not re-award badges
        // (the evaluator is idempotent on slug+shooter).
        return match ($this) {
            self::Draft => [self::PreRegistration, self::RegistrationOpen, self::RegistrationClosed, self::SquaddingOpen, self::Active],
            self::PreRegistration => [self::Draft, self::RegistrationOpen, self::RegistrationClosed],
            self::RegistrationOpen => [self::Draft, self::PreRegistration, self::RegistrationClosed],
            self::RegistrationClosed => [self::Draft, self::PreRegistration, self::RegistrationOpen, self::SquaddingOpen, self::SquaddingClosed, self::Active],
            self::SquaddingOpen => [self::RegistrationClosed, self::RegistrationOpen, self::SquaddingClosed, self::Active],
            self::SquaddingClosed => [self::SquaddingOpen, self::RegistrationClosed, self::Active],
            self::Active => [self::SquaddingClosed, self::SquaddingOpen, self::RegistrationClosed, self::Completed],
            self::Completed => [self::Active],
        };
    }

    public function shortDescription(): string
    {
        return match ($this) {
            self::Draft => 'Hidden from the public. Set up target sets, squads and settings here.',
            self::PreRegistration => 'Teased publicly as "coming soon". No sign-ups yet.',
            self::RegistrationOpen => 'Shooters can register and pay from the portal.',
            self::RegistrationClosed => 'No more sign-ups. You can still edit the list manually.',
            self::SquaddingOpen => 'Squads are being built. Self-squadding allowed if enabled.',
            self::SquaddingClosed => 'Squads locked to shooters. MD can still edit. Match not yet live.',
            self::Active => 'Scoring is live. Scoreboard accepts hits. Side Bet buy-in editable.',
            self::Completed => 'Scores finalised, achievements awarded, notifications sent.',
        };
    }

    public function transitionWarning(self $from): ?string
    {
        $forward = $this->ordinal() > $from->ordinal();

        return match ($this) {
            self::Draft => 'This match will disappear from the public portal.',
            self::PreRegistration => $forward
                ? 'The match becomes visible publicly as "coming soon".'
                : 'Pre-registered shooters keep their spot but no new sign-ups.',
            self::RegistrationOpen => 'Shooters with pre-registration will be notified registration is open.',
            self::RegistrationClosed => $forward
                ? 'Registration closes. Incomplete pre-registrations will be removed.'
                : 'Registration is frozen. The shooter list can still be edited manually.',
            self::SquaddingOpen => $forward
                ? 'Registered shooters will be notified squadding is open.'
                : 'Self-squadding re-opens to shooters.',
            self::SquaddingClosed => $forward
                ? 'Self-squadding locks for shooters. You can still edit squads. Match is not live yet.'
                : 'Match is no longer live. Scoreboard stops accepting new hits.',
            self::Active => $from === self::Completed
                ? 'Match will no longer be marked finished. Achievements already awarded and emails already sent stay in place.'
                : 'Scoring becomes live. Shooters can see the live scoreboard.',
            self::Completed => 'Achievements will be awarded and post-match emails scheduled. Side Bet buy-in will lock.',
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
            self::SquaddingClosed => 5,
            self::Active => 6,
            self::Completed => 7,
        };
    }
}

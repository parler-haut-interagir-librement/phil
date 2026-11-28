<?php

declare(strict_types=1);

namespace Phil\ShiftsBundle\Infrastructure\Twig\Components;

use Phil\Shifts\Roster\PlaceStatus;
use Phil\Shifts\Roster\Roster;
use Phil\ShiftsBundle\Application\Contract\CurrentPerson;
use Phil\ShiftsBundle\Application\Rosters;
use Phil\ShiftsBundle\Infrastructure\Http\ShiftsGuard;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * A shift as a card, `<twig:PhilShifts:ShiftCard id="desk-sat-am" />` or `<twig:PhilShifts:ShiftCard :roster="roster" />`:
 * its title, kind, window in the configured time zone, places left, where the person signed in stands, and the button
 * the mode gives them — sign up, say they are interested, withdraw —, each a form that works without JavaScript.
 *
 * A shift unknown renders nothing.
 */
#[AsTwigComponent(name: 'PhilShifts:ShiftCard', template: '@PhilShifts/components/ShiftCard.html.twig', exposePublicProps: false)]
final class ShiftCardComponent
{
    /** The identifier of the shift, read when no roster is given. */
    public string $id = '';

    /** The roster, when the page has it already. */
    public ?Roster $roster = null;

    /** Whether the card shows the buttons. */
    public bool $actions = true;

    public function __construct(
        private readonly Rosters $rosters,
        private readonly CurrentPerson $person,
        private readonly ShiftsGuard $guard,
        private readonly string $timeZone,
    ) {
    }

    /**
     * The roster given, else the one of the identifier; null for a shift unknown.
     *
     * @phpstan-impure
     */
    public function getCurrent(): ?Roster
    {
        return $this->roster ??= '' === $this->id ? null : $this->rosters->find($this->id);
    }

    /**
     * Where the person signed in stands on the roster: `confirmed`, `reserve`, `interested`, or null.
     *
     * @phpstan-impure
     */
    public function getStatus(): ?string
    {
        $person = $this->person->current();
        $roster = $this->getCurrent();

        return null === $person || !$roster instanceof Roster ? null : $roster->statusOf($person)?->value;
    }

    /**
     * @phpstan-impure
     */
    public function isConfirmed(): bool
    {
        return PlaceStatus::Confirmed->value === $this->getStatus();
    }

    /**
     * Whether volunteers may still join or leave the shift, by the server's clock.
     *
     * @phpstan-impure
     */
    public function isOpen(): bool
    {
        $roster = $this->getCurrent();

        return $roster instanceof Roster && $this->rosters->isOpen($roster);
    }

    public function getToken(): string
    {
        return $this->guard->token();
    }

    public function getZone(): string
    {
        return $this->timeZone;
    }
}

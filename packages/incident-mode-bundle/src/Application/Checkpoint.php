<?php

declare(strict_types=1);

namespace Phil\IncidentModeBundle\Application;

use DateTimeImmutable;
use Phil\IncidentMode\Activation;
use Phil\IncidentMode\Exception\InvalidIncident;
use Phil\IncidentMode\IncidentMode;
use Phil\IncidentMode\IncidentRestrictions;
use Phil\IncidentMode\Restriction;
use Phil\IncidentModeBundle\Application\Contract\CurrentMember;
use Phil\IncidentModeBundle\Application\Exception\SuspendedByIncident;
use Psr\Clock\ClockInterface;

/**
 * Where the application asks whether an incident stops what the member of the request is about to do: sign up, invite,
 * post a form. `IncidentRestrictions` decides, given the identifier `CurrentMember` reads — an established member is
 * spared from the restrictions the configuration lists, a visitor never is —, and the end of a suspension is the
 * latest end of the incidents in force that suspend it.
 *
 * It keeps nothing between calls: every answer reads the incidents in force at that moment.
 */
final readonly class Checkpoint
{
    public const int DEFAULT_STATUS = 503;

    public function __construct(
        private IncidentMode $incidents,
        private IncidentRestrictions $restrictions,
        private CurrentMember $member,
        private ClockInterface $clock,
        private int $status = self::DEFAULT_STATUS,
    ) {
    }

    /**
     * Whether the restriction applies to the member of the request now.
     *
     * @throws InvalidIncident when the name is not a restriction name
     *
     * @phpstan-impure
     */
    public function applies(string $restriction): bool
    {
        return $this->restrictions->appliesTo($restriction, $this->member->identifier());
    }

    /**
     * The first of the restrictions that applies to the member now, with its end; `null` when none does.
     *
     * @throws InvalidIncident when a name is not a restriction name
     *
     * @phpstan-impure
     */
    public function suspension(string ...$restrictions): ?Suspension
    {
        $inForce = null;
        $member = $this->member->identifier();

        foreach ($restrictions as $restriction) {
            $inForce ??= $this->incidents->inForceNow();
            $suspension = $this->until($restriction, $inForce);

            if ($suspension instanceof Suspension && $this->appliesTo($restriction, $member)) {
                return $suspension;
            }
        }

        return null;
    }

    /**
     * Every restriction that applies to the member now, each once, in the order the incidents started: what a banner
     * says.
     *
     * @return list<Suspension>
     *
     * @phpstan-impure
     */
    public function suspensions(): array
    {
        $inForce = $this->incidents->inForceNow();
        $member = $this->member->identifier();
        $names = [];

        foreach ($inForce as $activation) {
            foreach ($activation->restrictionNames() as $name) {
                $names[$name] = $name;
            }
        }

        $suspensions = [];

        foreach ($names as $name) {
            $suspension = $this->until($name, $inForce);

            if ($suspension instanceof Suspension && $this->appliesTo($name, $member)) {
                $suspensions[] = $suspension;
            }
        }

        return $suspensions;
    }

    /**
     * Refuses what the request asks for while one of the restrictions applies to its member.
     *
     * @throws SuspendedByIncident
     */
    public function guard(string ...$restrictions): void
    {
        $suspension = $this->suspension(...$restrictions);

        if ($suspension instanceof Suspension) {
            throw new SuspendedByIncident($suspension, $this->status, $suspension->secondsFrom($this->clock->now()));
        }
    }

    /**
     * Whether a restriction an incident suspends applies to the member: a visitor is never spared, so that only a
     * member asks `IncidentRestrictions` whether they are.
     */
    private function appliesTo(string $restriction, ?string $member): bool
    {
        return null === $member || $this->restrictions->appliesTo($restriction, $member);
    }

    /**
     * The end of the restriction: the latest end of the incidents in force that suspend it; `null` when none does.
     *
     * @param list<Activation> $inForce
     */
    private function until(string $restriction, array $inForce): ?Suspension
    {
        $named = Restriction::named($restriction);
        $until = null;

        foreach ($inForce as $activation) {
            if ($activation->suspends($named) && (!$until instanceof DateTimeImmutable || $activation->endsAt > $until)) {
                $until = $activation->endsAt;
            }
        }

        return $until instanceof DateTimeImmutable ? new Suspension($restriction, $until) : null;
    }
}

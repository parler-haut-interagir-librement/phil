<?php

declare(strict_types=1);

namespace Phil\OnboardingBundle\Domain\Model;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\Mapping as ORM;
use Phil\OnboardingBundle\Domain\Exception\InvalidProgress;
use Phil\OnboardingBundle\Domain\Flow\Identifier;
use Phil\OnboardingBundle\Domain\Flow\StepState;

/**
 * Where one user stands in one flow: the steps they completed or skipped and when, when they started, when they
 * finished every step, and whether they dismissed the flow. The user is the identifier the application names them
 * by, whatever its user class; a step not recorded is pending. Times are kept to the second, in UTC.
 */
#[ORM\Entity]
#[ORM\Table(name: self::TABLE)]
final class Progress
{
    public const string TABLE = 'phil_onboarding_progress';

    public const int MAX_USER = 320;

    /** @var array<string, array{state: string, at: string}> the recorded steps, by identifier */
    #[ORM\Column(name: 'steps', type: 'json')]
    private array $steps = [];

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private DateTimeImmutable $updatedAt;

    #[ORM\Column(name: 'finished_at', type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $finishedAt = null;

    #[ORM\Column(name: 'dismissed_at', type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $dismissedAt = null;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'user_identifier', length: self::MAX_USER)]
        public readonly string $user,
        #[ORM\Id]
        #[ORM\Column(name: 'flow', length: 64)]
        public readonly string $flow,
        #[ORM\Column(name: 'started_at', type: 'datetime_immutable')]
        private DateTimeImmutable $startedAt,
    ) {
        $this->updatedAt = $startedAt;
    }

    /**
     * @throws InvalidProgress when the user identifier is empty or longer than 320 bytes
     */
    public static function start(string $user, string $flow, DateTimeImmutable $now): self
    {
        if ('' === $user || \strlen($user) > self::MAX_USER) {
            throw InvalidProgress::because(\sprintf('A user identifier has 1 to %d bytes.', self::MAX_USER));
        }

        return new self($user, Identifier::check($flow, 'flow'), self::utc($now));
    }

    /**
     * The recorded steps, by identifier: their state and when it was recorded.
     *
     * @return array<string, array{state: string, at: string}>
     */
    public function steps(): array
    {
        return $this->steps;
    }

    public function stateOf(string $step): StepState
    {
        return StepState::tryFrom($this->steps[$step]['state'] ?? '') ?? StepState::Pending;
    }

    /**
     * When the step was completed or skipped; null while it is pending.
     */
    public function timeOf(string $step): ?DateTimeImmutable
    {
        if (StepState::Pending === $this->stateOf($step)) {
            return null;
        }

        $time = DateTimeImmutable::createFromFormat(\DATE_ATOM, $this->steps[$step]['at'] ?? '');

        return false === $time ? null : $time;
    }

    /**
     * Records a step completed, skipped before or not; says whether it was not completed yet.
     */
    public function complete(string $step, DateTimeImmutable $now): bool
    {
        return $this->record($step, StepState::Completed, $now);
    }

    /**
     * Records a pending step skipped; says whether it was pending. A completed step stays completed.
     */
    public function skip(string $step, DateTimeImmutable $now): bool
    {
        return StepState::Pending === $this->stateOf($step) && $this->record($step, StepState::Skipped, $now);
    }

    /**
     * Records the instant every step was done, the first time only; says whether it was the first time.
     */
    public function finish(DateTimeImmutable $now): bool
    {
        if ($this->finishedAt instanceof DateTimeImmutable) {
            return false;
        }

        $this->finishedAt = $this->touch($now);

        return true;
    }

    /**
     * Records that the user put the flow away; says whether it was not dismissed yet.
     */
    public function dismiss(DateTimeImmutable $now): bool
    {
        if ($this->dismissedAt instanceof DateTimeImmutable) {
            return false;
        }

        $this->dismissedAt = $this->touch($now);

        return true;
    }

    /**
     * Starts the flow again: every step pending, neither finished nor dismissed.
     */
    public function reset(DateTimeImmutable $now): void
    {
        $this->steps = [];
        $this->finishedAt = null;
        $this->dismissedAt = null;
        $this->startedAt = $this->touch($now);
    }

    public function isDismissed(): bool
    {
        return $this->dismissedAt instanceof DateTimeImmutable;
    }

    public function startedAt(): DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function finishedAt(): ?DateTimeImmutable
    {
        return $this->finishedAt;
    }

    public function dismissedAt(): ?DateTimeImmutable
    {
        return $this->dismissedAt;
    }

    private function record(string $step, StepState $state, DateTimeImmutable $now): bool
    {
        if ($state === $this->stateOf($step)) {
            return false;
        }

        $this->steps[$step] = ['state' => $state->value, 'at' => $this->touch($now)->format(\DATE_ATOM)];

        return true;
    }

    private function touch(DateTimeImmutable $now): DateTimeImmutable
    {
        return $this->updatedAt = self::utc($now);
    }

    private static function utc(DateTimeImmutable $time): DateTimeImmutable
    {
        $utc = $time->setTimezone(new DateTimeZone('UTC'));

        return $utc->setTime((int) $utc->format('H'), (int) $utc->format('i'), (int) $utc->format('s'));
    }
}

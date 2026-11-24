<?php

declare(strict_types=1);

namespace Phil\SchedulingPollBundle\Infrastructure\Memory;

use DateTimeImmutable;
use Override;
use Phil\Resource\ResourceReference;
use Phil\SchedulingPollBundle\Domain\Contract\RespondentRepository;
use Phil\SchedulingPollBundle\Domain\Contract\SchedulingPollRepository;
use Phil\SchedulingPollBundle\Domain\Model\Respondent;
use Phil\SchedulingPollBundle\Domain\Model\SchedulingPoll;

/**
 * The respondents in memory, for tests and scripts, reading their polls from the polls in memory; emptied between two
 * requests of a worker.
 */
final class InMemoryRespondentRepository implements RespondentRepository
{
    /** @var array<string, Respondent> */
    private array $respondents = [];

    public function __construct(
        private readonly SchedulingPollRepository $polls,
    ) {
    }

    #[Override]
    public function add(Respondent $respondent): void
    {
        $this->respondents[$respondent->id] = $respondent;
    }

    #[Override]
    public function get(string $id): ?Respondent
    {
        return $this->respondents[$id] ?? null;
    }

    #[Override]
    public function ofInvitation(string $invitation): ?Respondent
    {
        return array_find($this->respondents, static fn (Respondent $respondent): bool => $respondent->invitation === $invitation);
    }

    #[Override]
    public function ofPoll(string $poll): array
    {
        return array_values(array_filter($this->respondents, static fn (Respondent $respondent): bool => $respondent->poll === $poll));
    }

    #[Override]
    public function count(string $poll): int
    {
        return \count($this->ofPoll($poll));
    }

    #[Override]
    public function answer(string $id, array $answers, ?string $timezone, DateTimeImmutable $now): bool
    {
        $respondent = $this->respondents[$id] ?? null;
        $poll = null === $respondent ? null : $this->polls->get($respondent->poll);

        if (null === $respondent || !$poll instanceof SchedulingPoll || $respondent->isRevoked() || !$poll->isOpenAt($now)) {
            return false;
        }

        $respondent->answer($answers, $timezone, $now);

        return true;
    }

    #[Override]
    public function revoke(string $id, DateTimeImmutable $now): bool
    {
        $respondent = $this->respondents[$id] ?? null;

        if (null === $respondent || $respondent->isRevoked()) {
            return false;
        }

        $respondent->revoke($now);

        return true;
    }

    #[Override]
    public function claimNotice(string $id, DateTimeImmutable $now): bool
    {
        $respondent = $this->respondents[$id] ?? null;

        if (null === $respondent || $respondent->notifiedAt() instanceof DateTimeImmutable) {
            return false;
        }

        $respondent->notified($now);

        return true;
    }

    #[Override]
    public function releaseNotice(string $id): void
    {
        ($this->respondents[$id] ?? null)?->releaseNotice();
    }

    #[Override]
    public function eraseClosedBefore(DateTimeImmutable $cutoff, DateTimeImmutable $now): int
    {
        $erased = 0;

        foreach ($this->respondents as $respondent) {
            $closedAt = $this->polls->get($respondent->poll)?->closedAt();

            if (null === $respondent->erasedAt() && $closedAt instanceof DateTimeImmutable && $closedAt < $cutoff) {
                $respondent->erase($now);
                ++$erased;
            }
        }

        return $erased;
    }

    #[Override]
    public function ofPerson(?ResourceReference $account, ?string $email): array
    {
        return array_values(array_filter($this->respondents, static fn (Respondent $respondent): bool => self::isPerson($respondent, $account, $email)));
    }

    #[Override]
    public function forgetPerson(?ResourceReference $account, ?string $email): int
    {
        $forgotten = $this->ofPerson($account, $email);

        foreach ($forgotten as $respondent) {
            unset($this->respondents[$respondent->id]);
        }

        return \count($forgotten);
    }

    public function reset(): void
    {
        $this->respondents = [];
    }

    private static function isPerson(Respondent $respondent, ?ResourceReference $account, ?string $email): bool
    {
        $own = $respondent->account();

        return ($account instanceof ResourceReference && $own instanceof ResourceReference && $own->equals($account))
            || (null !== $email && null !== $respondent->email() && strtolower($respondent->email()) === strtolower($email));
    }
}

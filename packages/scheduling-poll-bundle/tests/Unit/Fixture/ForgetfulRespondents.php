<?php

declare(strict_types=1);

namespace Phil\SchedulingPollBundle\Tests\Unit\Fixture;

use DateTimeImmutable;
use Override;
use Phil\Resource\ResourceReference;
use Phil\SchedulingPollBundle\Domain\Contract\RespondentRepository;
use Phil\SchedulingPollBundle\Domain\Model\Respondent;

/**
 * A store of respondents that no longer finds a respondent by its identifier, as one erased between two reads.
 */
final readonly class ForgetfulRespondents implements RespondentRepository
{
    public function __construct(
        private RespondentRepository $store,
    ) {
    }

    #[Override]
    public function add(Respondent $respondent): void
    {
        $this->store->add($respondent);
    }

    #[Override]
    public function get(string $id): ?Respondent
    {
        return null;
    }

    #[Override]
    public function ofInvitation(string $invitation): ?Respondent
    {
        return $this->store->ofInvitation($invitation);
    }

    #[Override]
    public function ofPoll(string $poll): array
    {
        return $this->store->ofPoll($poll);
    }

    #[Override]
    public function count(string $poll): int
    {
        return $this->store->count($poll);
    }

    #[Override]
    public function answer(string $id, array $answers, ?string $timezone, DateTimeImmutable $now): bool
    {
        return $this->store->answer($id, $answers, $timezone, $now);
    }

    #[Override]
    public function revoke(string $id, DateTimeImmutable $now): bool
    {
        return $this->store->revoke($id, $now);
    }

    #[Override]
    public function claimNotice(string $id, DateTimeImmutable $now): bool
    {
        return $this->store->claimNotice($id, $now);
    }

    #[Override]
    public function releaseNotice(string $id): void
    {
        $this->store->releaseNotice($id);
    }

    #[Override]
    public function eraseClosedBefore(DateTimeImmutable $cutoff, DateTimeImmutable $now): int
    {
        return $this->store->eraseClosedBefore($cutoff, $now);
    }

    #[Override]
    public function ofPerson(?ResourceReference $account, ?string $email): array
    {
        return $this->store->ofPerson($account, $email);
    }

    #[Override]
    public function forgetPerson(?ResourceReference $account, ?string $email): int
    {
        return $this->store->forgetPerson($account, $email);
    }
}

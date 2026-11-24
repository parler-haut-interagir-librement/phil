<?php

declare(strict_types=1);

namespace Phil\SchedulingPollBundle\Domain\Contract;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use Phil\SchedulingPollBundle\Domain\Availability;
use Phil\SchedulingPollBundle\Domain\Model\Respondent;

/**
 * Where the respondents of the scheduling polls are kept.
 *
 * An answer is one conditional write, made only while the respondent is not revoked and its poll is neither closed
 * nor past its deadline, so that an answer racing the closing is kept before it or refused. The notice of the date
 * chosen is claimed in one conditional write too, so that two workers never send it twice.
 */
interface RespondentRepository
{
    public function add(Respondent $respondent): void;

    public function get(string $id): ?Respondent;

    public function ofInvitation(string $invitation): ?Respondent;

    /**
     * The respondents of a poll, in the order they joined, revoked ones included.
     *
     * @return list<Respondent>
     */
    public function ofPoll(string $poll): array;

    public function count(string $poll): int;

    /**
     * Records the answers, atomically, only while the respondent is not revoked and its poll is not closed and before
     * its deadline at `$now`. Returns whether they were recorded.
     *
     * @param array<string, Availability> $answers
     */
    public function answer(string $id, array $answers, ?string $timezone, DateTimeImmutable $now): bool;

    /**
     * Revokes a respondent; returns `false` for one revoked before or unknown.
     */
    public function revoke(string $id, DateTimeImmutable $now): bool;

    /**
     * Claims the notice of the date chosen, atomically, only when it was not claimed before. Returns whether this call
     * claimed it.
     */
    public function claimNotice(string $id, DateTimeImmutable $now): bool;

    /**
     * Gives back a notice that could not be sent, so that the message, tried again, claims it again.
     */
    public function releaseNotice(string $id): void;

    /**
     * Erases the name, the address and the time zone of the respondents of the polls closed before `$cutoff`, not
     * erased yet. Returns how many were erased.
     */
    public function eraseClosedBefore(DateTimeImmutable $cutoff, DateTimeImmutable $now): int;

    /**
     * The respondents with this account or this address, for an export of what is kept about a person.
     *
     * @return list<Respondent>
     */
    public function ofPerson(?ResourceReference $account, ?string $email): array;

    /**
     * Deletes the respondents with this account or this address. Returns how many were deleted.
     */
    public function forgetPerson(?ResourceReference $account, ?string $email): int;
}

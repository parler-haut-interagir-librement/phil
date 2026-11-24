<?php

declare(strict_types=1);

namespace Phil\FollowBundle\Domain\Contract;

use Phil\FollowBundle\Domain\Model\FollowRequest;
use Phil\FollowBundle\Domain\Status\RequestKind;
use Phil\NotificationsBundle\Domain\ValueObject\SubjectReference;

/**
 * The requests waiting for their approver, one per requester, subject and kind.
 */
interface FollowRequestRepository
{
    /**
     * @return bool false when the requester already waits on the same subject and kind, the request then not kept
     */
    public function add(FollowRequest $request): bool;

    public function find(string $id): ?FollowRequest;

    public function findPending(string $requester, SubjectReference $subject, RequestKind $kind): ?FollowRequest;

    /**
     * The requests an approver has to answer, oldest first; the cursor is the identifier of the last one read.
     *
     * @return list<FollowRequest>
     */
    public function pendingFor(string $approver, ?string $after, int $limit): array;

    /**
     * The requests a member sent and that still wait, oldest first; the cursor is the identifier of the last one read.
     *
     * @return list<FollowRequest>
     */
    public function sentBy(string $requester, ?string $after, int $limit): array;

    public function remove(string $id): bool;

    /**
     * Deletes every request the member sent or has to answer.
     */
    public function eraseMember(string $member): void;
}

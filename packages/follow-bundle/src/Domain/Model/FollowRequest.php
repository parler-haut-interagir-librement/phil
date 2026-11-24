<?php

declare(strict_types=1);

namespace Phil\FollowBundle\Domain\Model;

use DateTimeImmutable;
use Phil\FollowBundle\Domain\Exception\InvalidFollow;
use Phil\FollowBundle\Domain\Identifier;
use Phil\FollowBundle\Domain\Status\FollowLevel;
use Phil\FollowBundle\Domain\Status\RequestKind;
use Phil\FollowBundle\Domain\ValueObject\EventPreferences;
use Phil\NotificationsBundle\Domain\ValueObject\SubjectReference;

/**
 * A request waiting for its approver: to follow a subject they approve — a locked profile, a private group —, at the
 * level the requester chose, or to join them in a mutual relationship, the subject then being the approver's own
 * member subject. Accepted, it becomes a follow or a relationship; rejected, it is deleted.
 */
final class FollowRequest
{
    public readonly string $id;

    public readonly string $requester;

    public readonly string $approver;

    /**
     * @throws InvalidFollow when an identifier is of the wrong shape, or the requester approves their own request
     */
    public function __construct(
        string $id,
        string $requester,
        public readonly SubjectReference $subject,
        public readonly RequestKind $kind,
        string $approver,
        public readonly FollowLevel $level,
        public readonly EventPreferences $events,
        public readonly DateTimeImmutable $requestedAt,
    ) {
        $this->id = Identifier::id($id, 'request');
        $this->requester = Identifier::id($requester, 'requester');
        $this->approver = Identifier::id($approver, 'approver');

        if ($requester === $approver) {
            throw InvalidFollow::because('A member never approves their own request.');
        }
    }
}

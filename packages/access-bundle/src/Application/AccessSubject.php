<?php

declare(strict_types=1);

namespace Phil\AccessBundle\Application;

use Phil\AccessBundle\Application\Contract\CurrentMember;
use Phil\AccessBundle\Domain\ValueObject\MemberId;
use Phil\Resource\ResourceReference;
use Phil\ResourcePermissions\Subject;

/**
 * Who the checks of the request are for: the member an admin views the site as, else the signed-in member, else a
 * visitor, `visitor:anonymous`, whom only what is given to every visitor reaches.
 */
final readonly class AccessSubject
{
    public const string ANONYMOUS = 'anonymous';

    public function __construct(
        private CurrentMember $members,
        private ?ViewAs $viewAs = null,
    ) {
    }

    public function subject(): Subject
    {
        $member = $this->viewAs?->member() ?? $this->members->current();

        return Subject::of($member?->reference() ?? ResourceReference::of(AccessModel::VISITOR, self::ANONYMOUS));
    }

    /**
     * Whether the checks are an admin's view of another member, which only ever reads.
     */
    public function isViewingAs(): bool
    {
        return $this->viewAs?->member() instanceof MemberId;
    }
}

<?php

declare(strict_types=1);

namespace Phil\FollowBundle\Application;

use Phil\FollowBundle\Application\Contract\FollowAccess;
use Phil\FollowBundle\Application\Contract\FollowApproval;
use Phil\FollowBundle\Domain\Contract\FollowRepository;
use Phil\FollowBundle\Domain\Model\Follow;
use Phil\FollowBundle\Domain\Status\FollowLevel;
use Phil\FollowBundle\Domain\ValueObject\EventPreferences;
use Phil\NotificationsBundle\Domain\ValueObject\SubjectReference;
use Psr\Clock\ClockInterface;

/**
 * Following what one creates or replies to, when the application turns it on: the member follows the subject at the
 * configured level, unless they chose a level of their own already — ignoring included —, the subject asks for
 * approval, or they may not follow it. The application calls it where it creates and replies.
 */
final readonly class AutoFollow
{
    public function __construct(
        private FollowRepository $follows,
        private FollowApproval $approval,
        private FollowAccess $access,
        private ClockInterface $clock,
        private bool $onCreate = false,
        private bool $onReply = false,
        private FollowLevel $level = FollowLevel::Watching,
        private string $memberType = 'member',
    ) {
    }

    /**
     * @return bool whether the member follows the subject now
     */
    public function created(string $member, SubjectReference $subject): bool
    {
        return $this->onCreate && $this->follow($member, $subject);
    }

    /**
     * @return bool whether the member follows the subject now
     */
    public function replied(string $member, SubjectReference $subject): bool
    {
        return $this->onReply && $this->follow($member, $subject);
    }

    private function follow(string $member, SubjectReference $subject): bool
    {
        if (($subject->type === $this->memberType && $subject->id === $member)
            || $this->follows->find($member, $subject) instanceof Follow
            || null !== $this->approval->approverOf($subject, $member)
            || [] === $this->access->filter($subject, [$member])) {
            return false;
        }

        $this->follows->save(new Follow($member, $subject, $this->level, new EventPreferences(), $this->clock->now()));

        return true;
    }
}

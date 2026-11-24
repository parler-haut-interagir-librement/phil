<?php

declare(strict_types=1);

namespace Phil\FollowBundle\Application;

use Phil\FollowBundle\Domain\Model\Activity;
use Phil\FollowBundle\Domain\Model\Follow;
use Phil\NotificationsBundle\Domain\ValueObject\SubjectReference;

/**
 * Who among a page of follows hears of an activity: for each member, the follow of the nearest subject of the chain
 * decides — the subject itself, then its parents in order —, so that ignoring a thread wins over watching its forum,
 * and watching a thread wins over ignoring its forum. The member who did the activity never hears of it.
 */
final readonly class RecipientResolver
{
    /**
     * @param non-empty-list<SubjectReference> $chain
     * @param list<Follow>                     $follows
     *
     * @return list<string> the members to notify, in the order of the page
     */
    public function resolve(Activity $activity, array $chain, array $follows): array
    {
        $rank = [];

        foreach ($chain as $index => $subject) {
            $rank[$subject->key()] ??= $index;
        }

        // By member, the rank of the nearest follow and the follow; the member is read from the follow, never from the
        // key, which PHP turns into an integer for an identifier made of digits.
        /** @var array<array-key, array{int, Follow}> $nearest */
        $nearest = [];

        foreach ($follows as $follow) {
            $index = $rank[$follow->subject->key()] ?? null;

            if (null === $index || (isset($nearest[$follow->member]) && $nearest[$follow->member][0] <= $index)) {
                continue;
            }

            $nearest[$follow->member] = [$index, $follow];
        }

        $recipients = [];

        foreach ($nearest as [, $follow]) {
            $member = $follow->member;

            if ($member !== $activity->actor && $follow->notifies($activity->event, $activity->mentions($member))) {
                $recipients[] = $member;
            }
        }

        return $recipients;
    }
}

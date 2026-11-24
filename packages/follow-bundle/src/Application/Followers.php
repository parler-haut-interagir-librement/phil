<?php

declare(strict_types=1);

namespace Phil\FollowBundle\Application;

use Phil\FollowBundle\Domain\Contract\FollowerVisibilityRepository;
use Phil\FollowBundle\Domain\Contract\FollowRepository;
use Phil\FollowBundle\Domain\Model\Follow;
use Phil\NotificationsBundle\Domain\ValueObject\SubjectReference;

/**
 * Who follows a subject, as a viewer may see it: a member who hides their followers shows the list, and its count, to
 * themselves alone. Ignoring a subject is not following it. The count is a figure to show, never an order: the bundle
 * ranks nothing by it.
 */
final readonly class Followers
{
    public function __construct(
        private FollowRepository $follows,
        private FollowerVisibilityRepository $visibility,
        private bool $hiddenByDefault = false,
        private string $memberType = 'member',
    ) {
    }

    /**
     * @param string|null $viewer the member looking, null for a visitor
     * @param string|null $after  the last member of the page before
     */
    public function followersOf(SubjectReference $subject, ?string $viewer, ?string $after = null, int $limit = 50): FollowerList
    {
        if ($this->isHidden($subject, $viewer)) {
            return new FollowerList(hidden: true);
        }

        $limit = max(1, min($limit, Follows::MAX_PAGE));
        $follows = $this->follows->followersOf($subject, $after, $limit + 1);
        $more = \count($follows) > $limit;
        $members = array_map(static fn (Follow $follow): string => $follow->member, \array_slice($follows, 0, $limit));

        return new FollowerList(false, $members, $this->follows->countFollowers($subject), $more ? $members[$limit - 1] : null);
    }

    /**
     * How many follow the subject, or null when they are hidden from the viewer.
     */
    public function count(SubjectReference $subject, ?string $viewer): ?int
    {
        return $this->isHidden($subject, $viewer) ? null : $this->follows->countFollowers($subject);
    }

    public function isHidden(SubjectReference $subject, ?string $viewer): bool
    {
        return $subject->type === $this->memberType && $subject->id !== $viewer && $this->followersHidden($subject->id);
    }

    public function followersHidden(string $member): bool
    {
        return $this->visibility->hidden($member) ?? $this->hiddenByDefault;
    }

    public function hideFollowers(string $member, bool $hidden): void
    {
        $this->visibility->choose($member, $hidden);
    }
}

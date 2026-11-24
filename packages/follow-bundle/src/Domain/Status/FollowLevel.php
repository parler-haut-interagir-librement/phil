<?php

declare(strict_types=1);

namespace Phil\FollowBundle\Domain\Status;

/**
 * How closely a member follows a subject: every activity, only the activities that mention them, nothing at all — an
 * explicit choice that also stops what a followed parent would bring —, or a level of its own for each kind of event.
 */
enum FollowLevel: string
{
    case Watching = 'watching';

    case Mentions = 'mentions';

    case Ignoring = 'ignoring';

    case Custom = 'custom';

    /**
     * Whether an activity reaches the member at this level; `Custom` is read event by event and reaches no one here.
     */
    public function notifies(bool $mentioned): bool
    {
        return match ($this) {
            self::Watching => true,
            self::Mentions => $mentioned,
            self::Ignoring, self::Custom => false,
        };
    }

    /**
     * Whether the member counts among the subject's followers: ignoring a subject is not following it.
     */
    public function follows(): bool
    {
        return self::Ignoring !== $this;
    }
}

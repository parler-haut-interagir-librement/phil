<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Domain\Status;

/**
 * What a moderator decides on a target: every decision closes the reports open on it.
 *
 * - `Dismiss`: nothing to do; the content stays as it is.
 * - `Approve`: a held content — the first post of a member, a text the watched words held — is published.
 * - `Hide`: the content is hidden from the public, kept for its author and the moderation.
 * - `Restore`: a hidden content is shown again.
 * - `Remove`: the content is removed.
 * - `Warn`: the author is warned; the content stays as it is.
 *
 * `Hide`, `Remove` and `Warn` restrict the author: a statement of reasons tells them why.
 */
enum DecisionAction: string
{
    case Dismiss = 'dismiss';
    case Approve = 'approve';
    case Hide = 'hide';
    case Restore = 'restore';
    case Remove = 'remove';
    case Warn = 'warn';

    /**
     * Whether the target's package is asked to change the content.
     */
    public function changesTarget(): bool
    {
        return match ($this) {
            self::Approve, self::Hide, self::Restore, self::Remove => true,
            self::Dismiss, self::Warn => false,
        };
    }

    /**
     * Whether the decision restricts the author or their content, and so comes with a statement of reasons.
     */
    public function restricts(): bool
    {
        return match ($this) {
            self::Hide, self::Remove, self::Warn => true,
            self::Dismiss, self::Approve, self::Restore => false,
        };
    }
}

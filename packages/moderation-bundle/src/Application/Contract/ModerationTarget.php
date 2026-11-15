<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Application\Contract;

use Phil\ModerationBundle\Application\TargetView;
use Phil\ModerationBundle\Domain\Status\DecisionAction;

/**
 * What a package gives the moderation of its content: forum posts, comments, reactions, listings, messages, profiles.
 *
 * The package keeps its content and its visibility; the moderation describes it in the queue and asks the package to
 * apply what a moderator decided. A service that implements it is registered by autoconfiguration.
 */
interface ModerationTarget
{
    /**
     * The type of the targets, as reports name it: `forum.post`, `comment`.
     */
    public function type(): string;

    /**
     * What the queue shows of the content, or `null` when it no longer exists.
     */
    public function describe(string $id): ?TargetView;

    /**
     * Applies the action to the content: publishes it (`Approve`), hides it from the public (`Hide`), shows it again
     * (`Restore`) or removes it (`Remove`). Must be idempotent.
     */
    public function apply(string $id, DecisionAction $action): void;
}

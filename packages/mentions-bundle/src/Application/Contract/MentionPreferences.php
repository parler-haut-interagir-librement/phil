<?php

declare(strict_types=1);

namespace Phil\MentionsBundle\Application\Contract;

/**
 * Whether a member wants to be notified when they are mentioned, by the identifier their notifications go to.
 */
interface MentionPreferences
{
    public function notifies(string $recipient): bool;
}

<?php

declare(strict_types=1);

namespace Phil\MentionsBundle\Application;

use Override;
use Phil\MentionsBundle\Application\Contract\MentionPreferences;

/**
 * Every member is notified of their mentions; the channels stay their own choice in the notifications' preferences.
 */
final readonly class AlwaysNotify implements MentionPreferences
{
    #[Override]
    public function notifies(string $recipient): bool
    {
        return true;
    }
}

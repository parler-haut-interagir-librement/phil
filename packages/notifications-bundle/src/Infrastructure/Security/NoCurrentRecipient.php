<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Infrastructure\Security;

use Override;
use Phil\NotificationsBundle\Application\Contract\CurrentRecipient;

/**
 * No one: without Symfony Security nor a `CurrentRecipient` of the application, the centre is empty.
 */
final readonly class NoCurrentRecipient implements CurrentRecipient
{
    #[Override]
    public function id(): ?string
    {
        return null;
    }
}

<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Application\Contract;

/**
 * Whose notification centre the current request shows: the identifier notifications are sent to, or null for a visitor.
 */
interface CurrentRecipient
{
    public function id(): ?string;
}

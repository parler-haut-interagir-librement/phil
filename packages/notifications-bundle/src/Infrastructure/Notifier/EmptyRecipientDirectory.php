<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Infrastructure\Notifier;

use Override;
use Phil\NotificationsBundle\Application\Contract\RecipientDirectory;
use Symfony\Component\Notifier\Recipient\RecipientInterface;

/**
 * Knows no one, until the application names its own directory: the centre works, and the channels that need an address
 * — email, the digest, SMS — are skipped.
 */
final readonly class EmptyRecipientDirectory implements RecipientDirectory
{
    #[Override]
    public function contact(string $recipient): ?RecipientInterface
    {
        return null;
    }

    #[Override]
    public function displayName(string $id): ?string
    {
        return null;
    }
}

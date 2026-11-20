<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Tests\Unit\Fixture;

use ArrayObject;
use Override;
use Phil\NotificationsBundle\Application\Contract\RecipientDirectory;
use Symfony\Component\Notifier\Recipient\RecipientInterface;

/**
 * A directory that knows nobody and keeps every look-up of a contact.
 */
final readonly class CountingDirectory implements RecipientDirectory
{
    /**
     * @param ArrayObject<int, string> $asked
     */
    public function __construct(
        public ArrayObject $asked,
    ) {
    }

    #[Override]
    public function contact(string $recipient): ?RecipientInterface
    {
        $this->asked->append($recipient);

        return null;
    }

    #[Override]
    public function displayName(string $id): ?string
    {
        return null;
    }
}

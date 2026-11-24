<?php

declare(strict_types=1);

namespace Phil\FollowBundle\Tests\Functional\App;

use Override;
use Phil\NotificationsBundle\Application\Contract\RecipientDirectory;
use Symfony\Component\Notifier\Recipient\RecipientInterface;

/**
 * Names for the members of the tests, and no address.
 */
final readonly class People implements RecipientDirectory
{
    #[Override]
    public function contact(string $recipient): ?RecipientInterface
    {
        return null;
    }

    #[Override]
    public function displayName(string $id): ?string
    {
        return ['ann' => 'Ann', 'bob' => 'Bob'][$id] ?? null;
    }
}

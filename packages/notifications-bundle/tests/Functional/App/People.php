<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Tests\Functional\App;

use Override;
use Phil\NotificationsBundle\Application\Contract\RecipientDirectory;
use Symfony\Component\Notifier\Recipient\Recipient;
use Symfony\Component\Notifier\Recipient\RecipientInterface;

final readonly class People implements RecipientDirectory
{
    #[Override]
    public function contact(string $recipient): ?RecipientInterface
    {
        return '42' === $recipient ? new Recipient('ada@example.test') : null;
    }

    #[Override]
    public function displayName(string $id): ?string
    {
        return ['17' => 'Grace', '42' => 'Ada'][$id] ?? null;
    }
}

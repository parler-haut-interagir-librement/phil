<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Tests\Unit\Fixture;

use Override;
use Phil\NotificationsBundle\Application\Contract\RecipientDirectory;
use Symfony\Component\Notifier\Recipient\Recipient;
use Symfony\Component\Notifier\Recipient\RecipientInterface;

/**
 * A directory of a few people: their addresses and their names.
 */
final readonly class Directory implements RecipientDirectory
{
    /**
     * @param array<array-key, string> $emails
     * @param array<array-key, string> $names
     */
    public function __construct(
        private array $emails = ['42' => 'ada@example.test'],
        private array $names = ['17' => 'Grace', '42' => 'Ada'],
    ) {
    }

    #[Override]
    public function contact(string $recipient): ?RecipientInterface
    {
        return isset($this->emails[$recipient]) ? new Recipient($this->emails[$recipient]) : null;
    }

    #[Override]
    public function displayName(string $id): ?string
    {
        return $this->names[$id] ?? null;
    }
}

<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Tests\Unit\Fixture;

use DateTimeImmutable;
use Override;
use Phil\StandardWebhooks\ProcessedWebhookRepository;
use Phil\StandardWebhooks\WebhookId;

/**
 * The processed ids in memory, with when each was claimed.
 */
final class Processed implements ProcessedWebhookRepository
{
    /** @var array<string, string> */
    public array $claims = [];

    #[Override]
    public function claim(WebhookId $id, DateTimeImmutable $processedAt): bool
    {
        if (isset($this->claims[$id->value])) {
            return false;
        }

        $this->claims[$id->value] = $processedAt->format('Y-m-d H:i:s');

        return true;
    }
}

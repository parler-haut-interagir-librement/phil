<?php

declare(strict_types=1);

namespace Phil\ContactFormBundle\Application;

use Override;

/**
 * The recipients of the bundle's configuration, for every message.
 */
final readonly class ConfiguredRecipients implements Recipients
{
    /**
     * @param non-empty-list<string> $recipients
     */
    public function __construct(
        private array $recipients,
    ) {
    }

    #[Override]
    public function for(ContactMessage $message): array
    {
        return $this->recipients;
    }
}

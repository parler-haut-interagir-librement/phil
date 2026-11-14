<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Application\Fixture;

use Override;
use Psr\Log\AbstractLogger;
use Stringable;

/**
 * Keeps the messages of the log lines, their placeholders unreplaced.
 */
final class MessageLogger extends AbstractLogger
{
    /**
     * @var list<string>
     */
    public array $messages = [];

    #[Override]
    public function log($level, string|Stringable $message, array $context = []): void
    {
        $this->messages[] = (string) $message;
    }
}

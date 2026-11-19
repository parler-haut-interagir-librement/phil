<?php

declare(strict_types=1);

namespace Phil\CommerceSyndicationBundle\Tests\Unit\Support;

use Override;
use Phil\CommerceSyndicationBundle\Application\Port\MessageTransport;

/**
 * Answers each message with the next status of its list, and keeps what it was given.
 */
final class RecordingTransport implements MessageTransport
{
    /** @var list<array{string, array<string, string>, string}> */
    public array $sent = [];

    /**
     * @param list<int> $statuses
     */
    public function __construct(
        private array $statuses = [204],
    ) {
    }

    #[Override]
    public function send(string $url, array $headers, string $body): int
    {
        $this->sent[] = [$url, $headers, $body];

        return array_shift($this->statuses) ?? 204;
    }
}

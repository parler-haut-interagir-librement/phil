<?php

declare(strict_types=1);

namespace Phil\Migration\Tests\Unit\Fixtures;

use Phil\Migration\Publisher;
use RuntimeException;

/**
 * A publisher that keeps each message as its transport and text, and fails on the transports it is told to.
 */
final class RecordingPublisher implements Publisher
{
    /** @var list<array{string, string}> */
    public array $published = [];

    /**
     * @param list<string> $failing
     */
    public function __construct(
        private readonly array $failing = [],
    ) {
    }

    public function publish(string $transport, string $message): void
    {
        if (\in_array($transport, $this->failing, true)) {
            throw new RuntimeException($transport.' is down.');
        }

        $this->published[] = [$transport, $message];
    }
}

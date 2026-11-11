<?php

declare(strict_types=1);

namespace Phil\MigrationBundle\Tests\Functional\App;

use Phil\Migration\Publisher;

/**
 * Stands for the chatter: keeps each message as its transport and text.
 */
final class RecordingPublisher implements Publisher
{
    /** @var list<array{string, string}> */
    public array $published = [];

    public function publish(string $transport, string $message): void
    {
        $this->published[] = [$transport, $message];
    }
}

<?php

declare(strict_types=1);

namespace Phil\MonitoringBundle\Application\Queue;

/**
 * One Messenger transport as the queues page shows it: its name, whether failed messages land in it, and how many
 * messages it holds — null when the transport cannot count them.
 */
final readonly class QueueSummary
{
    public function __construct(
        public string $transport,
        public bool $failure,
        public ?int $messages,
    ) {
    }
}

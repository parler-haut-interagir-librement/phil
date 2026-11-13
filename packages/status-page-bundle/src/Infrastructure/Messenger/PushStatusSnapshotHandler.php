<?php

declare(strict_types=1);

namespace Phil\StatusPageBundle\Infrastructure\Messenger;

use Phil\StatusPageBundle\Application\Message\PushStatusSnapshot;
use Phil\StatusPageBundle\Application\Snapshot\PushReport;
use Phil\StatusPageBundle\Application\SnapshotPush;

/**
 * Publishes the snapshot a `PushStatusSnapshot` asks for. A target that fails is logged by the push and published
 * again by the next message; the handler does not throw for it, so one unreachable target does not hold the others
 * in a retry loop.
 */
final readonly class PushStatusSnapshotHandler
{
    public function __construct(
        private SnapshotPush $push,
    ) {
    }

    public function __invoke(PushStatusSnapshot $message): PushReport
    {
        return $this->push->push($message->target);
    }
}

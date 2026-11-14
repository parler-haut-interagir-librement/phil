<?php

declare(strict_types=1);

namespace Phil\AuditBundle\Infrastructure\Messenger;

use Phil\AuditBundle\Application\DashboardFeed;
use Phil\AuditBundle\Application\Message\AuditRecorded;

/**
 * Feeds the dashboards as rows are recorded: each `AuditRecorded` message sends what its segment recorded since the
 * feed's place. A message handled twice, or late, sends nothing twice: the place has moved past its row.
 */
final readonly class FeedOnRecorded
{
    public function __construct(
        private DashboardFeed $feed,
    ) {
    }

    public function __invoke(AuditRecorded $message): void
    {
        $this->feed->feedSegment($message->segment);
    }
}

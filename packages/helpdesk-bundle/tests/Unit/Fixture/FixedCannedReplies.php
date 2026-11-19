<?php

declare(strict_types=1);

namespace Phil\HelpdeskBundle\Tests\Unit\Fixture;

use Override;
use Phil\HelpdeskBundle\Application\CannedReply;
use Phil\HelpdeskBundle\Application\Contract\CannedReplies;
use Phil\HelpdeskBundle\Domain\Exception\CannedReplyNotFound;
use Phil\HelpdeskBundle\Domain\Model\Ticket;

/**
 * One canned reply, `thanks`, written with the queue and the locale of the request.
 */
final readonly class FixedCannedReplies implements CannedReplies
{
    #[Override]
    public function for(Ticket $ticket): array
    {
        return [new CannedReply('thanks', 'Thanks')];
    }

    #[Override]
    public function render(string $id, Ticket $ticket, ?string $locale = null): string
    {
        if ('thanks' !== $id) {
            throw CannedReplyNotFound::of($id);
        }

        return \sprintf('Thanks for writing to %s (%s).', $ticket->queue()->value, $locale ?? 'none');
    }
}

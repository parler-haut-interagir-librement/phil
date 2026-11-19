<?php

declare(strict_types=1);

namespace Phil\HelpdeskBundle\Application;

use Override;
use Phil\HelpdeskBundle\Application\Contract\CannedReplies;
use Phil\HelpdeskBundle\Domain\Exception\CannedReplyNotFound;
use Phil\HelpdeskBundle\Domain\Model\Ticket;

/**
 * The canned replies of a help desk that has none.
 */
final readonly class NoCannedReplies implements CannedReplies
{
    #[Override]
    public function for(Ticket $ticket): array
    {
        return [];
    }

    #[Override]
    public function render(string $id, Ticket $ticket, ?string $locale = null): string
    {
        throw CannedReplyNotFound::of($id);
    }
}

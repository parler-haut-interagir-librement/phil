<?php

declare(strict_types=1);

namespace Phil\HelpdeskBundle\Application\Contract;

use Phil\HelpdeskBundle\Application\CannedReply;
use Phil\HelpdeskBundle\Domain\Exception\CannedReplyNotFound;
use Phil\HelpdeskBundle\Domain\Model\Ticket;

/**
 * The canned replies of the help desk: those offered in the queue of a request, and the text of one, written for that
 * request. The bundle's own implementation reads Twig templates the configuration names; an adapter to the content
 * templates of the application replaces it.
 */
interface CannedReplies
{
    /**
     * @return list<CannedReply>
     */
    public function for(Ticket $ticket): array;

    /**
     * @throws CannedReplyNotFound when the reply does not exist or is not offered in the queue of the request
     */
    public function render(string $id, Ticket $ticket, ?string $locale = null): string;
}

<?php

declare(strict_types=1);

namespace Phil\Lti\Contract;

use Phil\Lti\Message\Context;
use Phil\Lti\Service\Nrps\Member;

/**
 * The contexts of the platform and their members, as the application keeps them: the platform's services read them
 * for a tool, and only in the contexts where the tool is placed.
 */
interface Roster
{
    /**
     * The context, when the tool of this client is placed in it; null otherwise, as for a context that does not exist.
     */
    public function context(string $clientId, string $contextId): ?Context;

    /**
     * The members of a context, in a stable order, from an offset; `limit` at most.
     *
     * @return list<Member>
     */
    public function members(string $contextId, ?string $role, int $limit, int $offset): array;
}

<?php

declare(strict_types=1);

namespace Phil\OpenBadgesBundle\Application\Contract;

use Phil\OpenBadgesBundle\Application\StatusListContent;

/**
 * The application's status lists: for each list it names, the purpose of its bits and the indexes of the credentials
 * revoked or suspended in it. The application assigns each revocable credential an index in a list when it issues it,
 * and keeps it.
 */
interface StatusLists
{
    /**
     * The content of a list, or null for a list the application does not have.
     */
    public function find(string $list): ?StatusListContent;
}

<?php

declare(strict_types=1);

namespace Phil\EmbedBundle\Application\Contract;

use Phil\Url\Url;

/**
 * The comments of a page carried by the forum of another site: the thread that holds them, created at the first visit
 * of a page of an allowed host. The bundle declares the port and ships no adapter.
 *
 * Invariants: a page of a host that is not allowed has no thread; the same page always has the same thread.
 */
interface RemoteCommentThreads
{
    /**
     * The address of the thread of the page, or null when its host is not allowed.
     */
    public function threadOf(Url $page): ?Url;
}

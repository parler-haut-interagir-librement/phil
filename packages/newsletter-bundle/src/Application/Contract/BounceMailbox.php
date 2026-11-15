<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Application\Contract;

/**
 * The mailbox the bounces come back to.
 */
interface BounceMailbox
{
    /**
     * The messages not read yet, raw, by their id in the mailbox.
     *
     * @return iterable<int|string, string>
     */
    public function unread(int $limit): iterable;

    /**
     * Marks a message as handled, so that it is not read again.
     */
    public function acknowledge(string $id): void;
}

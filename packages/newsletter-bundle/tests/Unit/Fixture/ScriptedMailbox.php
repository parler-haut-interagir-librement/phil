<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Tests\Unit\Fixture;

use Override;
use Phil\NewsletterBundle\Application\Contract\BounceMailbox;

/**
 * A mailbox of the messages it is given, that keeps what was acknowledged.
 */
final class ScriptedMailbox implements BounceMailbox
{
    /** @var list<string> */
    public array $acknowledged = [];

    public int $limit = 0;

    /**
     * @param array<int|string, string> $messages
     */
    public function __construct(
        private readonly array $messages,
    ) {
    }

    #[Override]
    public function unread(int $limit): iterable
    {
        $this->limit = $limit;

        yield from \array_slice($this->messages, 0, $limit, true);
    }

    #[Override]
    public function acknowledge(string $id): void
    {
        $this->acknowledged[] = $id;
    }
}

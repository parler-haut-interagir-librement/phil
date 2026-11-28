<?php

declare(strict_types=1);

namespace Phil\BulkActions\Run;

/**
 * What an action knows of the run while it changes one item. The idempotency key is the same for every attempt on the
 * item: an effect recorded under it in an outbox is recorded once, even when a chunk is applied again.
 */
final readonly class ItemContext
{
    public function __construct(
        public RunId $run,
        public string $item,
        public string $actor,
        public int $attempt,
    ) {
    }

    public function idempotencyKey(): string
    {
        return 'bulk:'.$this->run->value.':'.hash('sha256', $this->item);
    }
}

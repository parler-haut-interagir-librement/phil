<?php

declare(strict_types=1);

namespace Phil\BulkActions\Messenger;

/**
 * The message that asks for the next chunk of a run: the run's identifier and nothing else, so that a queue never
 * holds the items, the changes or a person's data.
 */
final readonly class ExecuteBulkRun
{
    public function __construct(public string $run)
    {
    }
}

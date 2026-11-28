<?php

declare(strict_types=1);

namespace Phil\BulkActions\Contract;

/**
 * The access rules of the application, asked for a given person rather than the current request: a run executes in a
 * worker, where no one is logged in.
 */
interface Authorizer
{
    /**
     * Whether the person may run the action on the target at all, asked at the selection, the preview, the
     * confirmation, every chunk and a retry.
     */
    public function mayRun(string $actor, string $target, BulkAction $action): bool;

    /**
     * Whether the person may apply the action to this item: its voter attribute, asked on each item.
     */
    public function mayApply(string $actor, BulkAction $action, object $item): bool;
}

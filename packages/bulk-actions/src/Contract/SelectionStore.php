<?php

declare(strict_types=1);

namespace Phil\BulkActions\Contract;

use Phil\BulkActions\Selection\PendingSelection;

/**
 * Keeps the selections waiting for a confirmation, by the fingerprint of their token.
 */
interface SelectionStore
{
    public function save(PendingSelection $selection): void;

    /**
     * Reads a selection without using it, for the preview.
     */
    public function peek(string $fingerprint): ?PendingSelection;

    /**
     * Reads and deletes a selection in one atomic step: of two confirmations of the same token, one gets it and the
     * other gets null.
     */
    public function take(string $fingerprint): ?PendingSelection;
}

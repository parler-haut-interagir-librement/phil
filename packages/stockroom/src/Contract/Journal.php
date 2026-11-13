<?php

declare(strict_types=1);

namespace Phil\Stockroom\Contract;

use Phil\Stockroom\Movement;

/**
 * The journal of the movements, written once, read the latest first.
 */
interface Journal
{
    public function append(Movement $movement): void;

    /**
     * The movements of an item, the latest first, at most so many.
     *
     * @return list<Movement>
     */
    public function ofItem(string $item, int $limit): array;

    /**
     * The movements of an event, a loan or an order, the latest first, at most so many.
     *
     * @return list<Movement>
     */
    public function ofReference(string $reference, int $limit): array;
}

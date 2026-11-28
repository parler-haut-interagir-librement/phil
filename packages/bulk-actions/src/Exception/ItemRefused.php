<?php

declare(strict_types=1);

namespace Phil\BulkActions\Exception;

use RuntimeException;

/**
 * Thrown by an action that will not change one item, such as an order already shipped: the item is reported as refused,
 * with the translation key given, and the other items go on.
 */
final class ItemRefused extends RuntimeException implements BulkActionsException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct(\sprintf('The item was refused: %s.', $reason));
    }

    /**
     * @param string $reason a translation key telling the person why, such as "products.refused.archived"
     */
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}

<?php

declare(strict_types=1);

namespace Phil\InventoryAlerts\Exception;

use LogicException;

/**
 * A purchase proposal approved or rejected when it is no longer waiting for a decision.
 */
final class ProposalAlreadyDecided extends LogicException implements InventoryAlertsException
{
    public static function forSupplier(string $supplier): self
    {
        return new self(\sprintf('The purchase proposal for supplier "%s" is already decided.', $supplier));
    }
}

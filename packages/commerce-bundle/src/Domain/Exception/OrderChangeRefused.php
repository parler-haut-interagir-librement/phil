<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Domain\Exception;

use DomainException;
use Phil\CommerceBundle\Domain\Change\OrderChangeState;
use Phil\DddBundle\Domain\Exception\DomainError;

/**
 * A change of a placed order that cannot be prepared or confirmed.
 */
final class OrderChangeRefused extends DomainException implements DomainError
{
    public static function notPrepared(string $id, OrderChangeState $state): self
    {
        return new self(\sprintf('The change %s is %s: only a prepared change is confirmed or abandoned.', $id, $state->value));
    }

    public static function stale(string $id, string $orderNumber, int $prepared, int $current): self
    {
        return new self(\sprintf('The change %s was prepared on revision %d of the order %s, which is at revision %d now: prepare it again.', $id, $prepared, $orderNumber, $current));
    }

    public static function otherOrder(string $id, string $orderNumber): self
    {
        return new self(\sprintf('The change %s is not a change of the order %s.', $id, $orderNumber));
    }

    public static function receiptIssued(string $orderNumber): self
    {
        return new self(\sprintf('The order %s has its receipt: a credit note and a new order replace it.', $orderNumber));
    }

    public static function notPlaced(string $orderNumber): self
    {
        return new self(\sprintf('The order %s is not placed: only a placed order is changed this way.', $orderNumber));
    }
}

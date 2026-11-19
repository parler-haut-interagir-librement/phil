<?php

declare(strict_types=1);

namespace Phil\CommerceWholesaleBundle\Domain\Exception;

use DomainException;
use Phil\DddBundle\Domain\Exception\DomainError;

/**
 * A quantity of a SKU its rule refuses, raised while a line is added or changed. It carries the messages of the
 * violations, translated as the validator translated them, to show the buyer.
 */
final class QuantityRefused extends DomainException implements DomainError
{
    /**
     * @param list<string> $messages
     */
    public function __construct(
        public readonly string $sku,
        public readonly int $quantity,
        public readonly array $messages,
    ) {
        parent::__construct(\sprintf('%d units of %s are refused: %s', $quantity, $sku, implode(' ', $messages)));
    }

    /**
     * @param list<string> $messages
     */
    public static function of(string $sku, int $quantity, array $messages): self
    {
        return new self($sku, $quantity, $messages);
    }
}

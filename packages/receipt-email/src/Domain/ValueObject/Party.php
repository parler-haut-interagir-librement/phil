<?php

declare(strict_types=1);

namespace Phil\ReceiptEmail\Domain\ValueObject;

use Phil\ReceiptEmail\Domain\Guard;

/**
 * Who a receipt names — the seller, the charity, the donor, an appraiser —: a name and the lines of an address, in
 * the order they are printed.
 */
final readonly class Party
{
    /**
     * @param list<string> $address
     */
    private function __construct(
        public string $name,
        public array $address,
    ) {
    }

    /**
     * @param list<string> $address at most ten lines
     */
    public static function of(string $name, array $address = []): self
    {
        return new self(Guard::text($name, 'A name'), Guard::lines($address, 'An address'));
    }
}

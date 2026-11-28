<?php

declare(strict_types=1);

namespace Phil\ReceiptEmail\Application;

use Phil\ReceiptEmail\Domain\Exception\InvalidReceipt;
use Symfony\Component\Mime\Address;

/**
 * Who a receipt is sent to, and the locale it is written in: `en`, `fr`, `fr_CA`.
 */
final readonly class Recipient
{
    public function __construct(
        public Address $address,
        public string $locale,
    ) {
        if (1 !== preg_match('/^[a-z]{2,3}(?:_[A-Za-z0-9]{2,8})?$/D', $locale)) {
            throw InvalidReceipt::because('A locale is a language code, then optionally "_" and a region, as fr_CA.');
        }
    }

    /**
     * The name to greet, `null` when the address has none.
     */
    public function name(): ?string
    {
        $name = trim($this->address->getName());

        return '' === $name ? null : $name;
    }
}

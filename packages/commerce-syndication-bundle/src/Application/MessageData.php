<?php

declare(strict_types=1);

namespace Phil\CommerceSyndicationBundle\Application;

use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * Reads the `data` of a received message field by field: a field of the wrong type refuses the message, which the
 * inbox answers as unreadable.
 */
final readonly class MessageData
{
    /**
     * @param array<mixed> $data
     */
    public function __construct(
        private array $data,
    ) {
    }

    public function string(string $key): string
    {
        $value = $this->data[$key] ?? null;

        return \is_string($value) ? $value : throw InvalidValue::because(\sprintf('"%s" is a string', $key));
    }

    public function optionalString(string $key): ?string
    {
        $value = $this->data[$key] ?? null;

        return null === $value || \is_string($value) ? $value : throw InvalidValue::because(\sprintf('"%s" is a string or null', $key));
    }

    public function int(string $key): int
    {
        $value = $this->data[$key] ?? null;

        return \is_int($value) ? $value : throw InvalidValue::because(\sprintf('"%s" is an integer', $key));
    }

    /**
     * The `lines` of a shipment or a return: a list of objects, each a `sku` and a `quantity`, summed by SKU.
     *
     * @return array<int|string, int> SKU → units; a numeric SKU is an integer key, as PHP makes it
     */
    public function quantities(): array
    {
        $lines = $this->data['lines'] ?? null;

        if (!\is_array($lines) || !array_is_list($lines)) {
            throw InvalidValue::because('"lines" is a list');
        }

        $quantities = [];

        foreach ($lines as $line) {
            $line = new self(\is_array($line) ? $line : throw InvalidValue::because('each line is an object'));
            $sku = $line->string('sku');
            $quantities[$sku] = ($quantities[$sku] ?? 0) + $line->int('quantity');
        }

        return $quantities;
    }
}

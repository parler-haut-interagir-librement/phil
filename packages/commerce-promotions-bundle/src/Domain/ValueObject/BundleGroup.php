<?php

declare(strict_types=1);

namespace Phil\CommercePromotionsBundle\Domain\ValueObject;

use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * One group of a bundle: a number of units taken from a list of products — *one rulebook*, *two dice sets out of these
 * four*. A bundle is complete when each of its groups holds its number of units.
 */
final readonly class BundleGroup
{
    /** The products a group lists, at most. */
    public const int MAX_SKUS = 100;

    /** The units a group asks for, at most. */
    public const int MAX_QUANTITY = 1_000;

    /**
     * @param non-empty-list<string> $skus
     */
    private function __construct(
        public array $skus,
        public int $quantity,
    ) {
    }

    /**
     * @param list<string> $skus the products whose units fill the group
     */
    public static function of(array $skus, int $quantity = 1): self
    {
        $skus = array_values(array_unique($skus));

        if ([] === $skus || \count($skus) > self::MAX_SKUS) {
            throw InvalidValue::because(\sprintf('A bundle group lists 1 to %d products; %d is not.', self::MAX_SKUS, \count($skus)));
        }

        foreach ($skus as $sku) {
            if (1 !== preg_match('/^[\x21-\x7E]{1,64}$/D', $sku)) {
                throw InvalidValue::because(\sprintf('"%s" is not a SKU: 1 to 64 visible ASCII characters.', $sku));
            }
        }

        if ($quantity < 1 || $quantity > self::MAX_QUANTITY) {
            throw InvalidValue::because(\sprintf('A bundle group asks for 1 to %d units; %d is not.', self::MAX_QUANTITY, $quantity));
        }

        return new self($skus, $quantity);
    }

    /**
     * The group as its promotion stores it.
     *
     * @return array{skus: non-empty-list<string>, quantity: int}
     */
    public function toArray(): array
    {
        return ['skus' => $this->skus, 'quantity' => $this->quantity];
    }

    /**
     * A group as its promotion stored it.
     *
     * @param array{skus: list<string>, quantity: int} $stored
     */
    public static function fromArray(array $stored): self
    {
        return self::of($stored['skus'], $stored['quantity']);
    }

    public function covers(string $sku): bool
    {
        return \in_array($sku, $this->skus, true);
    }
}

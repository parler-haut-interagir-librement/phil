<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Domain\Carrier;

use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * A parcel as a carrier prices it: its weight in grams and, when known, its length, width and height in millimetres.
 */
final readonly class Parcel
{
    private function __construct(
        public int $grams,
        public ?int $lengthMillimetres,
        public ?int $widthMillimetres,
        public ?int $heightMillimetres,
    ) {
    }

    public static function of(int $grams, ?int $lengthMillimetres = null, ?int $widthMillimetres = null, ?int $heightMillimetres = null): self
    {
        if ($grams < 1 || $grams > 1_000_000) {
            throw InvalidValue::because(\sprintf('A parcel weighs 1 to 1000000 grams; %d is refused.', $grams));
        }

        $sides = [$lengthMillimetres, $widthMillimetres, $heightMillimetres];
        $known = array_filter($sides, static fn (?int $side): bool => null !== $side);

        if ([] !== $known && 3 !== \count($known)) {
            throw InvalidValue::because('A parcel gives its three dimensions, or none.');
        }

        foreach ($known as $side) {
            if ($side < 1 || $side > 10_000) {
                throw InvalidValue::because(\sprintf('A side of a parcel measures 1 to 10000 millimetres; %d is refused.', $side));
            }
        }

        return new self($grams, $lengthMillimetres, $widthMillimetres, $heightMillimetres);
    }

    public function hasDimensions(): bool
    {
        return null !== $this->lengthMillimetres;
    }
}

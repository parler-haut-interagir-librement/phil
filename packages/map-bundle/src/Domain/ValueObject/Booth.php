<?php

declare(strict_types=1);

namespace Phil\MapBundle\Domain\ValueObject;

use Phil\MapBundle\Domain\Exception\InvalidMap;

/**
 * A booth of a floor plan: its identifier, its label, the rectangle it covers on the plan's image, in pixels from the
 * top-left corner, and the resource of phil/reservation it is booked as, when it is.
 */
final readonly class Booth
{
    public const int MAX_LABEL = 100;

    private function __construct(
        public string $id,
        public string $label,
        public int $x,
        public int $y,
        public int $width,
        public int $height,
        public ?string $resource,
    ) {
    }

    /**
     * @throws InvalidMap when the identifier or the label is empty or too long, or the rectangle has no area
     */
    public static function of(string $id, string $label, int $x, int $y, int $width, int $height, ?string $resource = null): self
    {
        if ('' === trim($id) || \strlen($id) > self::MAX_LABEL) {
            throw InvalidMap::because(\sprintf('A booth has an identifier of 1 to %d bytes.', self::MAX_LABEL));
        }

        if ('' === trim($label) || mb_strlen($label) > self::MAX_LABEL) {
            throw InvalidMap::because(\sprintf('A booth has a label of 1 to %d characters.', self::MAX_LABEL));
        }

        if ($x < 0 || $y < 0 || $width < 1 || $height < 1) {
            throw InvalidMap::because(\sprintf('The booth "%s" covers a rectangle inside the plan, at least one pixel wide and high.', $id));
        }

        if ('' === $resource) {
            throw InvalidMap::because(\sprintf('The resource of the booth "%s" is named, or null.', $id));
        }

        return new self($id, $label, $x, $y, $width, $height, $resource);
    }
}

<?php

declare(strict_types=1);

namespace Phil\CommerceProductTemplate;

use Phil\CommerceProductTemplate\Exception\InvalidDefinition;
use Phil\CommerceProductTemplate\Internal\Check;

/**
 * A zone of a face where something is printed: its position and size in millimetres from the top left corner of the
 * face, the resolution of its print file, and the rectangle it covers on the face's photos, for mockups.
 */
final readonly class PrintZone
{
    public const int DEFAULT_DPI = 300;

    private function __construct(
        public string $name,
        public float $x,
        public float $y,
        public float $width,
        public float $height,
        public int $dpi,
        public ?PhotoBox $photoBox,
    ) {
    }

    /**
     * @param string $name up to 32 lowercase letters, digits or "_", starting with a letter
     * @param int    $dpi  from 72 to 2400
     */
    public static function of(string $name, float $x, float $y, float $width, float $height, int $dpi = self::DEFAULT_DPI, ?PhotoBox $photoBox = null): self
    {
        Check::identifier($name, 'a print zone');

        if ($x < 0 || $y < 0) {
            throw InvalidDefinition::because(\sprintf('The print zone %s starts on its face, at 0 mm or more.', $name));
        }

        if ($width <= 0 || $height <= 0 || $width > 10_000 || $height > 10_000) {
            throw InvalidDefinition::because(\sprintf('The print zone %s measures more than 0 and at most 10 000 mm each way.', $name));
        }

        if ($dpi < 72 || $dpi > 2400) {
            throw InvalidDefinition::because(\sprintf('The print zone %s is printed at 72 to 2400 dpi, not %d.', $name, $dpi));
        }

        return new self($name, $x, $y, $width, $height, $dpi, $photoBox);
    }
}

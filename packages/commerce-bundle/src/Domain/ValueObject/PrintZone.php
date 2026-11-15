<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Domain\ValueObject;

use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * Where a text option is printed on the article: a named zone and its rectangle, in millimetres from the top left
 * corner of the printable area. A preview draws the text in it, and the composer of the print file fits it there.
 */
final readonly class PrintZone
{
    private function __construct(
        public string $name,
        public int $x,
        public int $y,
        public int $width,
        public int $height,
    ) {
    }

    public static function of(string $name, int $x, int $y, int $width, int $height): self
    {
        if (1 !== preg_match('/^[a-z][a-z0-9_]{0,31}$/D', $name)) {
            throw InvalidValue::because(\sprintf('"%s" is not the name of a print zone: up to 32 lowercase letters, digits or "_", starting with a letter.', $name));
        }

        if ($x < 0 || $y < 0) {
            throw InvalidValue::because(\sprintf('The print zone %s starts outside the printable area.', $name));
        }

        if ($width < 1 || $height < 1) {
            throw InvalidValue::because(\sprintf('The print zone %s has no area: its width and height are at least one millimetre.', $name));
        }

        return new self($name, $x, $y, $width, $height);
    }
}

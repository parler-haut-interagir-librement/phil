<?php

declare(strict_types=1);

namespace Phil\PrintCompositor;

use Phil\PrintCompositor\Exception\InvalidLayout;

/**
 * An sRGB colour with its opacity, each channel from 0 to 255; an alpha of 255 is opaque.
 */
final readonly class Colour
{
    public function __construct(
        public int $red,
        public int $green,
        public int $blue,
        public int $alpha = 255,
    ) {
        foreach (['red' => $red, 'green' => $green, 'blue' => $blue, 'alpha' => $alpha] as $channel => $value) {
            if ($value < 0 || $value > 255) {
                throw InvalidLayout::because(\sprintf('The %s channel of a colour goes from 0 to 255; %d given.', $channel, $value));
            }
        }
    }

    /**
     * A colour written `#RRGGBB` or `#RRGGBBAA`, the hash optional, in either case.
     */
    public static function fromHex(string $hex): self
    {
        if (1 !== preg_match('/^#?((?:[0-9a-f]{2}){3,4})$/iD', $hex, $match)) {
            throw InvalidLayout::because(\sprintf('A colour is written #RRGGBB or #RRGGBBAA; "%s" given.', $hex));
        }

        return new self(...array_map(static fn (string $pair): int => \intval($pair, 16), str_split($match[1], 2)));
    }

    /**
     * The colour written `#rrggbb`, or `#rrggbbaa` when it is not opaque.
     */
    public function toHex(): string
    {
        $hex = \sprintf('#%02x%02x%02x', $this->red, $this->green, $this->blue);

        return 255 === $this->alpha ? $hex : $hex.\sprintf('%02x', $this->alpha);
    }
}

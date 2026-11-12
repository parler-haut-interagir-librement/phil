<?php

declare(strict_types=1);

namespace Phil\SocialCards\Card;

use Phil\SocialCards\Exception\InvalidCardTemplate;
use Phil\SocialCards\Text;

/**
 * A colour of a card, written in hexadecimal as CSS does: `#rgb`, `#rgba`, `#rrggbb` or `#rrggbbaa`.
 */
final readonly class Color
{
    /**
     * @param int<0, 255> $red
     * @param int<0, 255> $green
     * @param int<0, 255> $blue
     * @param int<0, 255> $alpha 255 opaque, 0 transparent
     */
    public function __construct(
        public int $red,
        public int $green,
        public int $blue,
        public int $alpha = 255,
    ) {
    }

    public static function fromHex(string $hex): self
    {
        if (1 !== preg_match('/^#([0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/Di', $hex, $match)) {
            throw InvalidCardTemplate::because(\sprintf('"%s" is not a colour: #rgb, #rgba, #rrggbb or #rrggbbaa.', Text::excerpt($hex)));
        }

        $digits = $match[1];

        if (\strlen($digits) <= 4) {
            $digits = implode('', array_map(static fn (string $digit): string => $digit.$digit, str_split($digits)));
        }

        /** @var list<int<0, 255>> $channels three, or four with the alpha */
        $channels = array_map(hexdec(...), str_split($digits, 2));

        return new self(...$channels);
    }

    /**
     * The colour as `#rrggbbaa`, in lower case: the same colour always writes the same way.
     */
    public function hex(): string
    {
        return \sprintf('#%02x%02x%02x%02x', $this->red, $this->green, $this->blue, $this->alpha);
    }

    /**
     * The alpha of GD, 0 opaque to 127 transparent.
     *
     * @return int<0, 127>
     */
    public function gdAlpha(): int
    {
        /** @var int<0, 127> */
        return intdiv((255 - $this->alpha) * 127 + 127, 255);
    }
}

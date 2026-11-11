<?php

declare(strict_types=1);

namespace Phil\CommerceProductTemplate;

use Phil\CommerceProductTemplate\Exception\InvalidDefinition;
use Phil\CommerceProductTemplate\Internal\Check;

/**
 * What a selector shows for a value, most often a colour: a hexadecimal colour, or the photo of a sample when no
 * colour says it — a rainbow, a metallic finish.
 */
final readonly class Swatch
{
    private function __construct(
        public ?string $hex,
        public ?string $photo,
    ) {
    }

    /**
     * @param string $hex `#RRGGBB` or `#RRGGBBAA`, kept in uppercase
     */
    public static function colour(string $hex): self
    {
        if (1 !== preg_match('/^#(?:[0-9A-Fa-f]{6}|[0-9A-Fa-f]{8})$/D', $hex)) {
            throw InvalidDefinition::because(\sprintf('"%s" is not a colour: "#" and 6 or 8 hexadecimal digits.', $hex));
        }

        return new self(strtoupper($hex), null);
    }

    /**
     * @param string $path the photo of a sample, relative to the store of images
     */
    public static function photo(string $path): self
    {
        return new self(null, Check::path($path, 'a swatch'));
    }
}

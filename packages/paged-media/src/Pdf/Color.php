<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Pdf;

use Phil\PagedMedia\Exception\InvalidDocument;

/**
 * A colour for text, lines and fills: a grey level or an RGB triple, each component from 0 to 255.
 */
final readonly class Color
{
    /**
     * @param list<int> $components one grey level, or red, green and blue
     */
    private function __construct(
        private array $components,
    ) {
    }

    public static function gray(int $level): self
    {
        return new self([self::component($level)]);
    }

    public static function rgb(int $red, int $green, int $blue): self
    {
        return new self([self::component($red), self::component($green), self::component($blue)]);
    }

    /**
     * A colour written `#rrggbb` or `#rgb`.
     */
    public static function hex(string $hex): self
    {
        if (1 !== preg_match('/\A#(?:[0-9a-f]{3}){1,2}\z/i', $hex)) {
            throw InvalidDocument::because(\sprintf('A colour is written #rrggbb or #rgb; "%s" is not.', $hex));
        }

        $digits = substr($hex, 1);
        $pairs = 3 === \strlen($digits) ? array_map(static fn (string $digit): string => $digit.$digit, str_split($digits)) : str_split($digits, 2);

        return new self(array_map(static fn (string $pair): int => \intval($pair, 16), $pairs));
    }

    public static function black(): self
    {
        return new self([0]);
    }

    /**
     * The operator that sets this colour, for fills and text (`rg`, `g`) or for strokes (`RG`, `G`).
     */
    public function operator(bool $stroke): string
    {
        $values = implode(' ', array_map(static fn (int $component): string => Number::format($component / 255, 3), $this->components));
        $operator = 1 === \count($this->components) ? 'g' : 'rg';

        return $values.' '.($stroke ? strtoupper($operator) : $operator);
    }

    private static function component(int $value): int
    {
        if ($value < 0 || $value > 255) {
            throw InvalidDocument::because(\sprintf('A colour component is between 0 and 255; %d is not.', $value));
        }

        return $value;
    }
}

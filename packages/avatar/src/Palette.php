<?php

declare(strict_types=1);

namespace Phil\Avatar;

use Phil\Avatar\Exception\InvalidAvatar;

/**
 * The colors an avatar is drawn in: backgrounds to choose from, a light and a dark ink. Each background reaches the
 * WCAG AA contrast of normal text, 4.5:1, with at least one of the inks; the palette refuses one that does not, so
 * that initials are always readable.
 */
final readonly class Palette
{
    /** The contrast initials keep with their background: WCAG 2.2 AA for normal text. */
    public const float MIN_CONTRAST = 4.5;

    public const int MAX_BACKGROUNDS = 64;

    private const array BUILT_IN = [
        'default' => ['#1e5aa8', '#a3322c', '#2e7d4f', '#6b3fa0', '#b25e09', '#0f766e', '#9d174d', '#475569', '#f2c94c', '#9fd3c7', '#f4a6a6', '#c5b4e3'],
        'pastel' => ['#fde2e4', '#e2ece9', '#cddafd', '#fff1e6', '#dfe7fd', '#f0efeb', '#d8e2dc', '#fad2e1', '#bee1e6', '#e9edc9'],
        'vivid' => ['#d7263d', '#1b998b', '#2e294e', '#f46036', '#c5d86d', '#3a86ff', '#8338ec', '#ffbe0b', '#06d6a0', '#ef476f'],
        'earth' => ['#7f5539', '#9c6644', '#b08968', '#ddb892', '#606c38', '#283618', '#bc6c25', '#dda15e', '#6b705c', '#a5a58d'],
        'grayscale' => ['#111827', '#374151', '#4b5563', '#6b7280', '#9ca3af', '#d1d5db', '#e5e7eb', '#f3f4f6'],
    ];

    /** @var non-empty-list<Color> */
    public array $backgrounds;

    /**
     * @param list<Color> $backgrounds
     *
     * @throws InvalidAvatar
     */
    public function __construct(
        array $backgrounds,
        public Color $lightInk,
        public Color $darkInk,
    ) {
        if ([] === $backgrounds || \count($backgrounds) > self::MAX_BACKGROUNDS) {
            throw InvalidAvatar::because(\sprintf('A palette holds 1 to %d backgrounds.', self::MAX_BACKGROUNDS));
        }

        foreach ($backgrounds as $background) {
            if (max($background->contrastWith($lightInk), $background->contrastWith($darkInk)) < self::MIN_CONTRAST) {
                throw InvalidAvatar::because(\sprintf('The background %s reaches a contrast of 4.5:1 with neither ink.', $background->toHex()));
            }
        }

        $this->backgrounds = $backgrounds;
    }

    /**
     * A palette from colors written `#rrggbb`, with white and near-black inks unless others are given.
     *
     * @param list<string> $backgrounds
     *
     * @throws InvalidAvatar
     */
    public static function fromHex(array $backgrounds, string $lightInk = '#ffffff', string $darkInk = '#111111'): self
    {
        return new self(array_map(Color::fromHex(...), $backgrounds), Color::fromHex($lightInk), Color::fromHex($darkInk));
    }

    /**
     * One of the palettes the package ships: `default`, `pastel`, `vivid`, `earth` or `grayscale`.
     *
     * @throws InvalidAvatar
     */
    public static function named(string $name): self
    {
        return self::fromHex(self::BUILT_IN[$name] ?? throw InvalidAvatar::because('The palettes are default, pastel, vivid, earth and grayscale.'));
    }

    /**
     * The names of the palettes `named()` knows.
     *
     * @return list<string>
     */
    public static function names(): array
    {
        return array_keys(self::BUILT_IN);
    }

    /**
     * The ink of the higher contrast with a background.
     */
    public function inkFor(Color $background): Color
    {
        return $background->contrastWith($this->lightInk) >= $background->contrastWith($this->darkInk) ? $this->lightInk : $this->darkInk;
    }
}

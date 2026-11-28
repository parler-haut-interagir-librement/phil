<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Application\Rendering;

use Phil\EmailTemplatesBundle\Domain\Exception\InvalidTemplate;

/**
 * The colours and the font the layout and the email components draw with, in light and in dark. A colour is a
 * hexadecimal `#rgb` or `#rrggbb`; the font is a list of families of letters, digits, spaces, hyphens, commas and
 * quotes. Both are checked, since they are written into `style` attributes.
 */
final readonly class Theme
{
    /** The colours of the light scheme, by name. */
    public const array LIGHT = [
        'primary' => '#1d4ed8',
        'primary_text' => '#ffffff',
        'text' => '#1f2937',
        'muted' => '#6b7280',
        'background' => '#f3f4f6',
        'surface' => '#ffffff',
        'panel' => '#eef2ff',
        'border' => '#e5e7eb',
    ];

    /** The colours of the dark scheme, by name, for the clients that apply `prefers-color-scheme`. */
    public const array DARK = [
        'primary' => '#60a5fa',
        'primary_text' => '#0b1220',
        'text' => '#e5e7eb',
        'muted' => '#9ca3af',
        'background' => '#0b1220',
        'surface' => '#111827',
        'panel' => '#1e293b',
        'border' => '#374151',
    ];

    public const string FONT = "-apple-system, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif";

    private const string COLOUR = '/^#(?:[0-9a-fA-F]{3}){1,2}$/D';

    private const string FAMILY = "/^[A-Za-z0-9 ,'\"-]{1,200}$/D";

    /**
     * @param array<string, string> $light
     * @param array<string, string> $dark
     */
    private function __construct(
        public array $light,
        public array $dark,
        public string $font,
    ) {
    }

    /**
     * @param array<string, string> $light the colours that replace those of `LIGHT`
     * @param array<string, string> $dark  the colours that replace those of `DARK`
     *
     * @throws InvalidTemplate when a colour or the font is out of shape
     */
    public static function create(array $light = [], array $dark = [], string $font = self::FONT): self
    {
        $errors = [];

        foreach (['light' => [$light, self::LIGHT], 'dark' => [$dark, self::DARK]] as $scheme => [$given, $defaults]) {
            foreach ($given as $name => $colour) {
                if (!\array_key_exists($name, $defaults)) {
                    $errors[] = \sprintf('The %s theme has no colour "%s"; its colours are %s.', $scheme, $name, implode(', ', array_keys($defaults)));
                } elseif (1 !== preg_match(self::COLOUR, $colour)) {
                    $errors[] = \sprintf('The %s colour "%s" is "#rgb" or "#rrggbb".', $scheme, $name);
                }
            }
        }

        if (1 !== preg_match(self::FAMILY, $font)) {
            $errors[] = 'The font is a list of families of letters, digits, spaces, hyphens, commas and quotes.';
        }

        if ([] !== $errors) {
            throw InvalidTemplate::withErrors($errors);
        }

        return new self(array_replace(self::LIGHT, $light), array_replace(self::DARK, $dark), $font);
    }

    public function light(string $name): string
    {
        return $this->light[$name] ?? self::LIGHT['text'];
    }

    public function dark(string $name): string
    {
        return $this->dark[$name] ?? self::DARK['text'];
    }

    public static function isColour(string $value): bool
    {
        return 1 === preg_match(self::COLOUR, $value);
    }
}

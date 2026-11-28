<?php

declare(strict_types=1);

namespace Phil\EmailPreview\Style;

/**
 * What the rules read of CSS: the declarations of a `style` attribute and a length in pixels. The last declaration of a
 * property wins, as in a browser; what is not a plain length in pixels is not a length here.
 *
 * @internal
 */
final class InlineStyle
{
    private function __construct()
    {
    }

    /**
     * The declarations of a `style` attribute, by property in lowercase, their values trimmed.
     *
     * @return array<string, string>
     */
    public static function declarations(string $style): array
    {
        $declarations = [];

        foreach (explode(';', $style) as $declaration) {
            $colon = strpos($declaration, ':');

            if (false === $colon) {
                continue;
            }

            $property = strtolower(trim(substr($declaration, 0, $colon)));
            $value = trim(substr($declaration, $colon + 1));

            if ('' !== $property && '' !== $value) {
                $declarations[$property] = $value;
            }
        }

        return $declarations;
    }

    /**
     * A length in pixels — `600`, `600px`, `600px !important` —, or `null` for a percentage, another unit or a word.
     */
    public static function pixels(string $value): ?float
    {
        $value = strtolower(trim($value));

        if (str_ends_with($value, '!important')) {
            $value = rtrim(substr($value, 0, -10));
        }

        if (str_ends_with($value, 'px')) {
            $value = substr($value, 0, -2);
        }

        return is_numeric($value) ? (float) $value : null;
    }
}

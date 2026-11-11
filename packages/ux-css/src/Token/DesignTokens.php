<?php

declare(strict_types=1);

namespace Phil\UxCss\Token;

use JsonException;
use Phil\UxCss\Color\Oklch;
use Phil\UxCss\Exception\InvalidColor;

/**
 * The custom properties of a sheet in the format of the Design Tokens Community Group (2025.10), the CSS staying the
 * source: `--phil-space-sm` is the token `sm` of the group `space`, a name of one word the `$root` of its group.
 *
 * A property that is another one, `var(--phil-brand-40)`, is an alias, `{brand.40}`; a `calc()` is computed at the
 * defaults; a `light-dark()` gives its light value, the dark one going into the token's extension, with the property
 * and its CSS as written. Colours, lengths in `px` or `rem`, durations, numbers, font weights and families, cubic
 * Béziers and shadows have a type of the format; a property of any other value, such as `65ch` or `12%`, is left out
 * and named in the extension of the file.
 */
final readonly class DesignTokens
{
    /**
     * The key of the extensions this class writes, after the domain of PHIL.
     */
    public const string EXTENSION = 'ca.ph-il.phil-ux';

    private const string NAME = '/^[A-Za-z0-9_-]+$/D';

    /**
     * @param array<string, array<string, array<string, mixed>>> $groups  each group by name, each token by name
     * @param list<string>                                       $skipped the properties left out
     */
    private function __construct(
        private array $groups,
        private array $skipped,
    ) {
    }

    /**
     * The token files of phil-ux under its `css/` directory, in the order of their layers.
     *
     * @return list<string>
     */
    public static function sources(string $directory): array
    {
        return [$directory.'/tokens/palette.css', $directory.'/tokens/colors.css', $directory.'/tokens/dimensions.css'];
    }

    /**
     * The properties of `$sheet` whose name starts with `$prefix`.
     *
     * @throws InvalidColor when an OKLCH colour cannot be read
     */
    public static function fromSheet(TokenSheet $sheet, string $prefix = '--phil-'): self
    {
        $groups = [];
        $skipped = [];

        foreach ($sheet->names() as $property) {
            $path = self::path($property, $prefix);
            $value = $sheet->value($property);
            $token = null === $path || null === $value ? null : self::token($sheet, $prefix, $property, $value);

            if (null === $path || null === $token) {
                $skipped[] = $property;

                continue;
            }

            $groups[$path[0]][$path[1]] = $token;
        }

        return new self(self::zeroLengths($groups), $skipped);
    }

    /**
     * The tokens, as the JSON of the format holds them; a name of digits, such as `05` or `10`, is a key of the array
     * all the same.
     *
     * @return array<array-key, mixed>
     */
    public function toArray(): array
    {
        return ['$description' => 'The tokens of phil-ux, generated from its CSS, which stays the source.']
            + $this->groups
            + ['$extensions' => [self::EXTENSION => ['skipped' => $this->skipped]]];
    }

    /**
     * The file: the format's JSON, each group an object even when its names are all digits.
     *
     * @throws JsonException
     */
    public function toJson(): string
    {
        $document = array_map(static fn (mixed $group): mixed => \is_array($group) && array_is_list($group) && [] !== $group ? (object) $group : $group, $this->toArray());

        return json_encode((object) $document, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR)."\n";
    }

    /**
     * The properties left out, their value having no type of the format.
     *
     * @return list<string>
     */
    public function skipped(): array
    {
        return $this->skipped;
    }

    /**
     * The group and the name of a property, or `null` when it is outside the prefix or cannot name a token.
     *
     * @return array{string, string}|null
     */
    private static function path(string $property, string $prefix): ?array
    {
        if (!str_starts_with($property, $prefix)) {
            return null;
        }

        $parts = explode('-', substr($property, \strlen($prefix)), 2);
        $group = $parts[0];
        $name = $parts[1] ?? '$root';

        return 1 === preg_match(self::NAME, $group) && ('$root' === $name || 1 === preg_match(self::NAME, $name)) ? [$group, $name] : null;
    }

    /**
     * @return array<string, mixed>|null
     *
     * @throws InvalidColor
     */
    private static function token(TokenSheet $sheet, string $prefix, string $property, string $css): ?array
    {
        $typed = self::typed($sheet, $prefix, self::path($property, $prefix)[0] ?? '', $css, 0);

        if (null === $typed) {
            return null;
        }

        [$type, $value, $dark] = $typed;

        return ['$type' => $type, '$value' => $value, '$extensions' => [self::EXTENSION => ['property' => $property, 'css' => $css, ...$dark]]];
    }

    /**
     * The type and the value of a property's CSS, and its dark value when it is a `light-dark()` of two values of one
     * type; `null` when it has no type of the format.
     *
     * @return array{string, mixed, array{dark?: mixed}}|null
     *
     * @throws InvalidColor
     */
    private static function typed(TokenSheet $sheet, string $prefix, string $group, string $css, int $depth): ?array
    {
        $arguments = self::lightDark($css);

        if (null === $arguments) {
            $value = self::value($sheet, $prefix, $group, $css, $depth);

            return null === $value ? null : [$value[0], $value[1], []];
        }

        $light = self::value($sheet, $prefix, $group, $arguments[0], $depth);
        $dark = self::value($sheet, $prefix, $group, $arguments[1], $depth);

        if (null === $light || null === $dark || $light[0] !== $dark[0]) {
            return null;
        }

        return [$light[0], $light[1], ['dark' => $dark[1]]];
    }

    /**
     * The type and the value of a CSS value, or `null` when it has no type of the format.
     *
     * @return array{string, mixed}|null
     *
     * @throws InvalidColor
     */
    private static function value(TokenSheet $sheet, string $prefix, string $group, string $css, int $depth): ?array
    {
        if ($depth > Calc::MAX_DEPTH) {
            return null;
        }

        if (1 === preg_match('/^var\(\s*(--[A-Za-z0-9_-]+)\s*\)\z/', $css, $match)) {
            return self::alias($sheet, $prefix, $match[1], $depth);
        }

        if (str_starts_with(strtolower($css), 'oklch(')) {
            return ['color', self::oklch(Oklch::fromCss($css))];
        }

        return match ($group) {
            'font' => CssValue::fontFamily($css),
            'shadow' => self::shadow($sheet, $prefix, $css, $depth),
            default => str_starts_with(strtolower($css), 'cubic-bezier(') ? CssValue::cubicBezier($css) : self::quantity($sheet, $group, $css),
        };
    }

    /**
     * @return array{string, string}|null
     *
     * @throws InvalidColor
     */
    private static function alias(TokenSheet $sheet, string $prefix, string $property, int $depth): ?array
    {
        $path = self::path($property, $prefix);
        $value = $sheet->value($property);

        if (null === $path || null === $value) {
            return null;
        }

        $target = self::typed($sheet, $prefix, $path[0], $value, $depth + 1);

        return null === $target ? null : [$target[0], '{'.$path[0].'.'.$path[1].'}'];
    }

    /**
     * @return array{string, string}|null
     */
    private static function lightDark(string $css): ?array
    {
        if (1 !== preg_match('/^\s*light-dark\((.*)\)\s*$/is', $css, $match)) {
            return null;
        }

        $arguments = CssValue::split($match[1], ',');

        return 2 === \count($arguments) ? [$arguments[0], $arguments[1]] : null;
    }

    /**
     * The value of a colour, written or an alias; `null` for anything else.
     *
     * @throws InvalidColor
     */
    private static function color(TokenSheet $sheet, string $prefix, string $css, int $depth): mixed
    {
        $value = self::value($sheet, $prefix, '', $css, $depth);

        return 'color' === ($value[0] ?? null) ? $value[1] : null;
    }

    /**
     * @return array{colorSpace: string, components: list<float|int>, alpha?: float|int}
     */
    private static function oklch(Oklch $color): array
    {
        $value = ['colorSpace' => 'oklch', 'components' => [CssValue::number($color->lightness), CssValue::number($color->chroma), CssValue::number($color->hue)]];

        if (1.0 !== $color->alpha) {
            $value['alpha'] = CssValue::number($color->alpha);
        }

        return $value;
    }

    /**
     * @return array{string, mixed}|null
     */
    private static function quantity(TokenSheet $sheet, string $group, string $css): ?array
    {
        $quantity = Calc::evaluate($css, $sheet->value(...));

        if (!$quantity instanceof Quantity) {
            return null;
        }

        return match ($quantity->unit) {
            '' => 'weight' === $group ? self::fontWeight($quantity->value) : ['number', CssValue::number($quantity->value)],
            'px', 'rem' => ['dimension', ['value' => CssValue::number($quantity->value), 'unit' => $quantity->unit]],
            'ms', 's' => ['duration', ['value' => CssValue::number($quantity->value), 'unit' => $quantity->unit]],
            default => null,
        };
    }

    /**
     * @return array{string, int}|null
     */
    private static function fontWeight(float $weight): ?array
    {
        return $weight >= 1 && $weight <= 1000 && 0.0 === fmod($weight, 1.0) ? ['fontWeight', (int) $weight] : null;
    }

    /**
     * One shadow or several, each two to four lengths, `inset` perhaps, and a colour.
     *
     * @return array{string, mixed}|null
     *
     * @throws InvalidColor
     */
    private static function shadow(TokenSheet $sheet, string $prefix, string $css, int $depth): ?array
    {
        $layers = [];

        foreach (CssValue::split($css, ',') as $layer) {
            $shadow = self::shadowLayer($sheet, $prefix, $layer, $depth);

            if (null === $shadow) {
                return null;
            }

            $layers[] = $shadow;
        }

        return 1 === \count($layers) ? ['shadow', $layers[0]] : ['shadow', $layers];
    }

    /**
     * @return array<string, mixed>|null
     *
     * @throws InvalidColor
     */
    private static function shadowLayer(TokenSheet $sheet, string $prefix, string $layer, int $depth): ?array
    {
        $lengths = [];
        $color = null;
        $inset = false;

        foreach (CssValue::split($layer, ' ') as $part) {
            $length = Calc::evaluate($part, $sheet->value(...));

            if ('inset' === strtolower($part)) {
                $inset = true;
            } elseif ($length instanceof Quantity && (\in_array($length->unit, ['px', 'rem'], true) || ($length->isNumber() && 0.0 === $length->value))) {
                $lengths[] = ['value' => CssValue::number($length->value), 'unit' => $length->isNumber() ? 'px' : $length->unit];
            } elseif (null === $color && null !== ($found = self::color($sheet, $prefix, $part, $depth + 1))) {
                $color = $found;
            } else {
                return null;
            }
        }

        if (null === $color || \count($lengths) < 2 || \count($lengths) > 4) {
            return null;
        }

        $zero = ['value' => 0, 'unit' => 'px'];
        $shadow = ['color' => $color, 'offsetX' => $lengths[0], 'offsetY' => $lengths[1], 'blur' => $lengths[2] ?? $zero, 'spread' => $lengths[3] ?? $zero];

        return $inset ? [...$shadow, 'inset' => true] : $shadow;
    }

    /**
     * A plain `0` among the lengths of its group, as `--phil-radius-none`, is a length of `0px`.
     *
     * @param array<string, array<string, array<string, mixed>>> $groups
     *
     * @return array<string, array<string, array<string, mixed>>>
     */
    private static function zeroLengths(array $groups): array
    {
        foreach ($groups as $name => $tokens) {
            $types = array_map(static fn (array $token): string => \is_string($token['$type'] ?? null) ? $token['$type'] : '', $tokens);

            if (!\in_array('dimension', $types, true) || [] !== array_diff($types, ['dimension', 'number'])) {
                continue;
            }

            foreach ($tokens as $token => $value) {
                if ('number' === $value['$type'] && 0 === $value['$value']) {
                    $groups[$name][$token]['$type'] = 'dimension';
                    $groups[$name][$token]['$value'] = ['value' => 0, 'unit' => 'px'];
                }
            }
        }

        return $groups;
    }
}

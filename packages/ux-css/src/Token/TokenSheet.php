<?php

declare(strict_types=1);

namespace Phil\UxCss\Token;

use Phil\UxCss\Color\Oklch;
use Phil\UxCss\Exception\InvalidColor;
use Phil\UxCss\Exception\UnreadableSheet;

/**
 * The custom properties of one or more stylesheets, the later declaration of a name winning, as the cascade does for
 * the same selector: the token files of phil-ux, then the overrides of a site.
 *
 * Only declarations are read; selectors and at-rules are not, so a sheet that sets a token differently per selector
 * is read as its last declaration.
 */
final readonly class TokenSheet
{
    /**
     * Beyond this many `var()` followed in a row, a value is taken for a cycle and resolves to nothing.
     */
    private const int MAX_DEPTH = 16;

    /**
     * @param array<string, string> $declarations
     */
    private function __construct(private array $declarations)
    {
    }

    /**
     * @throws UnreadableSheet
     */
    public static function fromFiles(string ...$paths): self
    {
        $sheets = [];

        foreach ($paths as $path) {
            $contents = is_file($path) && is_readable($path) ? file_get_contents($path) : false;

            if (false === $contents) {
                throw UnreadableSheet::at($path);
            }

            $sheets[] = $contents;
        }

        return self::fromCss(...$sheets);
    }

    public static function fromCss(string ...$sheets): self
    {
        $declarations = [];

        foreach ($sheets as $css) {
            $css = (string) preg_replace('#/\*.*?\*/#s', '', $css);
            preg_match_all('/(--[A-Za-z0-9_-]+)\s*:\s*([^;{}]+?)\s*(?:;|(?=}))/', $css, $matches, \PREG_SET_ORDER);

            foreach ($matches as $match) {
                $declarations[$match[1]] = $match[2];
            }
        }

        return new self($declarations);
    }

    public function has(string $name): bool
    {
        return \array_key_exists($name, $this->declarations);
    }

    /**
     * The value as written, `var()` and `light-dark()` included.
     */
    public function value(string $name): ?string
    {
        return $this->declarations[$name] ?? null;
    }

    /**
     * The names declared, in the order they were first declared.
     *
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->declarations);
    }

    /**
     * The tones of the palette: the properties named `--phil-<hue>-<two digits>` whose value is an OKLCH colour.
     *
     * @return list<Tone>
     *
     * @throws InvalidColor
     */
    public function tones(): array
    {
        $tones = [];

        foreach ($this->declarations as $name => $value) {
            if (1 === preg_match('/^--phil-([a-z]+)-(\d{2})$/D', $name, $parts) && str_starts_with(strtolower($value), 'oklch(')) {
                $tones[] = new Tone($parts[1], (int) $parts[2], Oklch::fromCss($value));
            }
        }

        return $tones;
    }

    /**
     * The roles of the grid: each `<role>` for which `--phil-color-<role>-fill-quiet` is declared.
     *
     * @return list<string>
     */
    public function roles(): array
    {
        $roles = [];

        foreach (array_keys($this->declarations) as $name) {
            if (1 === preg_match('/^--phil-color-([a-z0-9-]+)-fill-quiet$/D', $name, $parts)) {
                $roles[] = $parts[1];
            }
        }

        return $roles;
    }

    /**
     * The colour a property has in a scheme, following `var()`, their fallbacks and `light-dark()`; null when it is
     * not declared, ends on something other than an OKLCH colour, or loops.
     *
     * @throws InvalidColor
     */
    public function resolve(string $name, Scheme $scheme): ?Oklch
    {
        $value = $this->declarations[$name] ?? null;

        return null === $value ? null : $this->resolveValue($value, $scheme, 0);
    }

    /**
     * @throws InvalidColor
     */
    private function resolveValue(string $value, Scheme $scheme, int $depth): ?Oklch
    {
        if ($depth > self::MAX_DEPTH) {
            return null;
        }

        $value = trim($value);

        if (1 === preg_match('/^light-dark\((.*)\)$/is', $value, $parts)) {
            $arguments = $this->splitArguments($parts[1]);

            if (2 !== \count($arguments)) {
                return null;
            }

            return $this->resolveValue(Scheme::Light === $scheme ? $arguments[0] : $arguments[1], $scheme, $depth + 1);
        }

        if (1 === preg_match('/^var\((.*)\)$/is', $value, $parts)) {
            $arguments = $this->splitArguments($parts[1], 2);
            $referenced = $this->declarations[$arguments[0]] ?? $arguments[1] ?? null;

            return null === $referenced ? null : $this->resolveValue($referenced, $scheme, $depth + 1);
        }

        return str_starts_with(strtolower($value), 'oklch(') ? Oklch::fromCss($value) : null;
    }

    /**
     * The arguments of a CSS function, split on the commas outside nested parentheses, each trimmed.
     *
     * @return non-empty-list<string>
     */
    private function splitArguments(string $arguments, int $limit = \PHP_INT_MAX): array
    {
        $parts = [];
        $depth = 0;
        $current = '';

        foreach (str_split($arguments) as $character) {
            if (',' === $character && 0 === $depth && \count($parts) < $limit - 1) {
                $parts[] = trim($current);
                $current = '';

                continue;
            }

            $depth += match ($character) {
                '(' => 1,
                ')' => -1,
                default => 0,
            };
            $current .= $character;
        }

        $parts[] = trim($current);

        return $parts;
    }
}

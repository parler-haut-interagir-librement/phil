<?php

declare(strict_types=1);

namespace Phil\TimedText;

use Phil\TimedText\Exception\InvalidCue;

/**
 * The WebVTT settings of a cue — `vertical`, `line`, `position`, `size`, `align` and `region` — in the order they were
 * written. SRT, JSON and HTML have none: their readers leave them empty and their writers drop them.
 */
final readonly class CueSettings
{
    private const string PERCENT = '(?:100(?:\.0+)?|\d{1,2}(?:\.\d+)?)%';

    /**
     * @param array<string, string> $values by name, in the order written
     *
     * @throws InvalidCue when a name is unknown or a value is not one WebVTT allows for it
     */
    public function __construct(public array $values = [])
    {
        foreach ($values as $name => $value) {
            if (!self::isValid($name, $value)) {
                throw new InvalidCue(\sprintf('"%s:%s" is not a WebVTT cue setting.', mb_substr($name, 0, 40), mb_substr($value, 0, 40)));
            }
        }
    }

    /**
     * Reads the settings after the timings of a cue. A setting WebVTT does not know, or with a value it does not
     * allow, is dropped and named in the second element, as a WebVTT parser ignores it; a repeated one keeps its last
     * value.
     *
     * @return array{self, list<string>}
     */
    public static function parse(string $text): array
    {
        $values = [];
        $rejected = [];

        $settings = preg_split('/[ \t]+/', trim($text, " \t"), -1, \PREG_SPLIT_NO_EMPTY);

        foreach (false === $settings ? [] : $settings as $setting) {
            $parts = explode(':', $setting, 2);

            if (2 === \count($parts) && self::isValid($parts[0], $parts[1])) {
                unset($values[$parts[0]]);
                $values[$parts[0]] = $parts[1];
            } else {
                $rejected[] = $setting;
            }
        }

        return [new self($values), $rejected];
    }

    public static function isValid(string $name, string $value): bool
    {
        $pattern = match ($name) {
            'vertical' => '(?:rl|lr)',
            'line' => '(?:auto|-?\d{1,5}|'.self::PERCENT.')(?:,(?:start|center|end))?',
            'position' => '(?:auto|'.self::PERCENT.')(?:,(?:line-left|center|line-right))?',
            'size' => self::PERCENT,
            'align' => '(?:start|center|end|left|right)',
            'region' => '[^\s]{1,200}',
            default => null,
        };

        return null !== $pattern && !str_contains($value, '-->') && 1 === preg_match('/^'.$pattern.'$/D', $value);
    }

    public function get(string $name): ?string
    {
        return $this->values[$name] ?? null;
    }

    public function isEmpty(): bool
    {
        return [] === $this->values;
    }

    /**
     * The settings as a timing line writes them, `align:start line:10%`.
     */
    public function toString(): string
    {
        $written = [];

        foreach ($this->values as $name => $value) {
            $written[] = $name.':'.$value;
        }

        return implode(' ', $written);
    }
}

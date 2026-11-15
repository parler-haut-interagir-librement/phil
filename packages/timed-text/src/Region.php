<?php

declare(strict_types=1);

namespace Phil\TimedText;

use Phil\TimedText\Exception\InvalidCue;

/**
 * A WebVTT region: an identifier the cues name in their `region` setting, and its `width`, `lines`, `regionanchor`,
 * `viewportanchor` and `scroll` settings in the order written.
 */
final readonly class Region
{
    private const string PERCENT = '(?:100(?:\.0+)?|\d{1,2}(?:\.\d+)?)%';

    /**
     * @param array<string, string> $settings by name, without `id`
     *
     * @throws InvalidCue when the identifier is not one token or a setting is not one WebVTT allows
     */
    public function __construct(
        public string $id,
        public array $settings = [],
    ) {
        if (1 !== preg_match('/^[^\s]{1,200}$/D', $id) || str_contains($id, '-->')) {
            throw new InvalidCue('The identifier of a region is one token, without "-->".');
        }

        foreach ($settings as $name => $value) {
            if (!self::isValid($name, $value)) {
                throw new InvalidCue(\sprintf('"%s:%s" is not a WebVTT region setting.', mb_substr($name, 0, 40), mb_substr($value, 0, 40)));
            }
        }
    }

    /**
     * Reads the lines of a `REGION` block; null without a valid identifier. A setting WebVTT does not know is dropped
     * and named in the second element.
     *
     * @param list<string> $lines
     *
     * @return array{?self, list<string>}
     */
    public static function parse(array $lines): array
    {
        $id = null;
        $settings = [];
        $rejected = [];

        $written = preg_split('/\s+/', implode(' ', $lines), -1, \PREG_SPLIT_NO_EMPTY);

        foreach (false === $written ? [] : $written as $setting) {
            $parts = explode(':', $setting, 2);

            if (2 === \count($parts) && 'id' === $parts[0] && 1 === preg_match('/^[^\s]{1,200}$/D', $parts[1]) && !str_contains($parts[1], '-->')) {
                $id = $parts[1];
            } elseif (2 === \count($parts) && self::isValid($parts[0], $parts[1])) {
                unset($settings[$parts[0]]);
                $settings[$parts[0]] = $parts[1];
            } else {
                $rejected[] = $setting;
            }
        }

        return [null === $id ? null : new self($id, $settings), $rejected];
    }

    public static function isValid(string $name, string $value): bool
    {
        $pattern = match ($name) {
            'width' => self::PERCENT,
            'lines' => '\d{1,4}',
            'regionanchor', 'viewportanchor' => self::PERCENT.','.self::PERCENT,
            'scroll' => 'up',
            default => null,
        };

        return null !== $pattern && 1 === preg_match('/^'.$pattern.'$/D', $value);
    }

    /**
     * The lines of the block that defines it, without the `REGION` line.
     *
     * @return list<string>
     */
    public function lines(): array
    {
        $lines = ['id:'.$this->id];

        foreach ($this->settings as $name => $value) {
            $lines[] = $name.':'.$value;
        }

        return $lines;
    }
}

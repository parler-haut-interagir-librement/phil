<?php

declare(strict_types=1);

namespace Phil\TimedText\Internal;

/**
 * One piece of a cue text: a run of text, an opening or closing tag, or a timestamp tag. Not part of the public API.
 *
 * @internal
 */
final readonly class CueToken
{
    public const string TEXT = 'text';

    public const string OPEN = 'open';

    public const string CLOSE = 'close';

    public const string TIMESTAMP = 'timestamp';

    /**
     * @param list<string> $classes
     */
    public function __construct(
        public string $kind,
        public string $value,
        public string $name = '',
        public array $classes = [],
        public string $annotation = '',
    ) {
    }

    /**
     * @return list<self>
     */
    public static function split(string $text): array
    {
        preg_match_all('/<([^<>]*)>|[^<]+|</', $text, $matches, \PREG_SET_ORDER);
        $tokens = [];

        foreach ($matches as $match) {
            $tokens[] = isset($match[1]) ? self::tag($match[0], $match[1]) : new self(self::TEXT, $match[0]);
        }

        return $tokens;
    }

    private static function tag(string $whole, string $inside): self
    {
        if (1 === preg_match('/^\d/', $inside)) {
            return new self(self::TIMESTAMP, $whole);
        }

        if (1 !== preg_match('/^(\/?)([A-Za-z]+)((?:\.[^\s.]+)*)(?:\s+(.*))?$/sD', $inside, $match)) {
            return new self(self::TEXT, $whole);
        }

        return new self(
            '/' === $match[1] ? self::CLOSE : self::OPEN,
            $whole,
            $match[2],
            '' === $match[3] ? [] : array_values(array_filter(explode('.', $match[3]), static fn (string $class): bool => '' !== $class)),
            trim($match[4] ?? ''),
        );
    }
}

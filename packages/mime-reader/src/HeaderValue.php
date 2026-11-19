<?php

declare(strict_types=1);

namespace Phil\MimeReader;

use Phil\MimeReader\Internal\Charset;
use Phil\MimeReader\Internal\EncodedWords;

/**
 * A header value with parameters, as `Content-Type` and `Content-Disposition` carry them: the value before the first
 * semicolon, then the parameters by name in lowercase. A parameter split in sections or given with its character set
 * (RFC 2231) is joined and decoded; one written as encoded words (RFC 2047), as some mailers do for file names, is
 * decoded too. Every parameter is valid UTF-8.
 */
final readonly class HeaderValue
{
    /**
     * @param array<string, string> $parameters
     */
    public function __construct(
        public string $value,
        public array $parameters = [],
    ) {
    }

    /**
     * Reads a value and at most `$limit` parameters; a segment without `=` and a name that is not a token are skipped.
     */
    public static function parse(string $text, int $limit = 32): self
    {
        $segments = self::segments($text);
        $value = trim(array_shift($segments));
        $plain = [];
        /** @var array<string, array<int, array{string, bool}>> $sections */
        $sections = [];

        foreach ($segments as $segment) {
            $equals = strpos($segment, '=');

            if (false === $equals) {
                continue;
            }

            $name = strtolower(trim(substr($segment, 0, $equals)));
            $raw = trim(substr($segment, $equals + 1));

            if (1 !== preg_match('/^([!#$%&\'+.0-9^_`a-z{|}~-]+?)(?:\*(\d{1,3}))?(\*)?$/', $name, $match)) {
                continue;
            }

            $base = $match[1];

            if (!isset($plain[$base]) && !isset($sections[$base]) && \count($plain) + \count($sections) >= $limit) {
                continue;
            }

            $extended = isset($match[3]);
            $text = $extended ? $raw : self::unquote($raw);

            if ('' === ($match[2] ?? '') && !$extended) {
                $plain[$base] = $text;
            } else {
                $sections[$base][(int) ($match[2] ?? 0)] = [$text, $extended];
            }
        }

        $parameters = [];

        foreach ($plain as $name => $text) {
            $parameters[$name] = EncodedWords::decode($text);
        }

        foreach ($sections as $name => $pieces) {
            $parameters[$name] = self::joined($pieces);
        }

        return new self($value, $parameters);
    }

    public function parameter(string $name): ?string
    {
        return $this->parameters[strtolower($name)] ?? null;
    }

    /**
     * The sections in their order, those marked extended percent-decoded, the set and language of the first removed,
     * then the whole converted from that set.
     *
     * @param array<int, array{string, bool}> $pieces
     */
    private static function joined(array $pieces): string
    {
        ksort($pieces);
        $charset = null;
        $bytes = '';
        $first = true;

        foreach ($pieces as [$text, $extended]) {
            if ($extended && $first && 1 === preg_match("/^([^']*)'[^']*'(.*)$/s", $text, $match)) {
                $charset = $match[1];
                $text = $match[2];
            }

            $bytes .= $extended ? rawurldecode($text) : $text;
            $first = false;
        }

        return Charset::toUtf8($bytes, $charset);
    }

    /**
     * The value split at the semicolons outside quotes.
     *
     * @return non-empty-list<string>
     */
    private static function segments(string $value): array
    {
        $segments = [''];
        $quoted = false;
        $length = \strlen($value);

        for ($i = 0; $i < $length; ++$i) {
            $char = $value[$i];

            if ('\\' === $char && $quoted) {
                $segments[\count($segments) - 1] .= $char.substr($value, ++$i, 1);
            } elseif (';' === $char && !$quoted) {
                $segments[] = '';
            } else {
                $quoted = '"' === $char ? !$quoted : $quoted;
                $segments[\count($segments) - 1] .= $char;
            }
        }

        return $segments;
    }

    private static function unquote(string $value): string
    {
        if (\strlen($value) >= 2 && str_starts_with($value, '"') && str_ends_with($value, '"')) {
            return (string) preg_replace('/\\\\(.)/s', '$1', substr($value, 1, -1));
        }

        return $value;
    }
}

<?php

declare(strict_types=1);

namespace Phil\ErrorTracking;

use Phil\ErrorTracking\Internal\Text;

/**
 * A message without the values that change from one occurrence to the next, so that `User 42 not found` and
 * `User 43 not found` fall in the same group: identifiers, e-mail and web addresses, quoted texts, hexadecimal and
 * decimal numbers each become a placeholder.
 */
final readonly class MessageNormalizer
{
    public const int MAX_LENGTH = 512;

    private const array PATTERNS = [
        '/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i',
        '/[a-z0-9._%+-]+@[a-z0-9-]+(?:\.[a-z0-9-]+)+/i',
        '#\b[a-z][a-z0-9+.-]*://[^\s"\'<>]+#i',
        '/"[^"]*"|\'[^\']*\'/',
        '/\b0x[0-9a-f]+\b|\b(?=[0-9a-f]*\d)[0-9a-f]{12,}\b/i',
        '/\d+(?:\.\d+)?/',
    ];

    private const array PLACEHOLDERS = ['<uuid>', '<email>', '<url>', '<string>', '<hex>', '<n>'];

    public function normalize(string $message): string
    {
        return (string) preg_replace(self::PATTERNS, self::PLACEHOLDERS, Text::clip($message, self::MAX_LENGTH));
    }
}

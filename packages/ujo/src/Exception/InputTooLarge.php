<?php

declare(strict_types=1);

namespace Phil\Ujo\Exception;

use InvalidArgumentException;

/**
 * An HTML fragment longer than the ceiling of the inline sanitizer it was given to, to be sanitized or converted to
 * text, refused whole before it is read: a fragment is never cut to fit. The ceiling is the `maxBytes` of
 * `Html\InlineSanitizer`, `InlineSanitizer::DEFAULT_MAX_BYTES` unless configured — the ceiling of `Json\JsonDecoder`,
 * so that no fragment of a document it reads is refused.
 */
final class InputTooLarge extends InvalidArgumentException implements UjoException
{
    public function __construct(
        string $message,
        public readonly int $bytes,
        public readonly int $limit,
    ) {
        parent::__construct($message);
    }

    public static function of(int $bytes, int $limit): self
    {
        return new self(\sprintf('The input is %d bytes long, above the limit of %d bytes.', $bytes, $limit), $bytes, $limit);
    }

    /**
     * Refuses an input longer than `$limit` bytes.
     *
     * @throws self when the input is longer than `$limit` bytes
     */
    public static function check(string $input, int $limit): void
    {
        $bytes = \strlen($input);

        if ($bytes > $limit) {
            throw self::of($bytes, $limit);
        }
    }
}

<?php

declare(strict_types=1);

namespace Phil\Typography\Exception;

use InvalidArgumentException;

/**
 * An HTML fragment, a document or a text longer than the ceiling of the typographer it was given to, refused whole
 * before it is parsed: a text is never cut to fit. The ceiling is the `maxBytes` of `Typographer` and of
 * `Typographers`, `Typographer::DEFAULT_MAX_BYTES` unless configured.
 */
final class InputTooLarge extends InvalidArgumentException implements TypographyException
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

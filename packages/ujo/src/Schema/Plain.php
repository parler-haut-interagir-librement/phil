<?php

declare(strict_types=1);

namespace Phil\Ujo\Schema;

use Override;
use Phil\Ujo\Contract\Sized;
use Phil\Ujo\Exception\InvalidDocument;

/**
 * A text taken as it is — code, a name, a formula —, escaped when rendered; with a length limit in characters, and a
 * pattern, when the field has them.
 */
final readonly class Plain implements Sized
{
    public function __construct(
        private ?int $maxLength = null,
        private ?string $pattern = null,
    ) {
    }

    /**
     * A name: 1 to 64 letters, digits, `_` or `-`.
     */
    public static function token(): self
    {
        return new self(null, '/^[A-Za-z0-9_-]{1,64}$/D');
    }

    /**
     * A CSS length — `20px`, `-3cm`, `1.5em`, `50%` — or `0` or `auto`.
     */
    public static function length(): self
    {
        return new self(null, '/^(?:-?\d{1,4}(?:\.\d{1,3})?(?:px|em|rem|%|mm|cm|in|pt|ch|vw|vh)|0|auto)$/D');
    }

    /**
     * A percentage, `-25%` to `100%`.
     */
    public static function percentage(): self
    {
        return new self(null, '/^-?\d{1,3}(?:\.\d{1,2})?%$/D');
    }

    /**
     * A colour, `#rrggbb`.
     */
    public static function color(): self
    {
        return new self(null, '/^#[0-9A-Fa-f]{6}$/D');
    }

    #[Override]
    public function normalize(mixed $value, string $path, Context $context): string
    {
        if (!\is_string($value)) {
            throw InvalidDocument::at($path, 'a text is expected');
        }

        if (null !== $this->maxLength && mb_strlen($value) > $this->maxLength) {
            throw InvalidDocument::at($path, \sprintf('the text is longer than %d characters', $this->maxLength));
        }

        if (null !== $this->pattern && 1 !== preg_match($this->pattern, $value)) {
            throw InvalidDocument::at($path, \sprintf('"%s" is not of the expected form', $value));
        }

        return $value;
    }

    /**
     * The characters of the text, its white space at both ends left out.
     */
    #[Override]
    public function size(mixed $value): int
    {
        return \is_string($value) ? self::visibleLength($value) : 0;
    }

    #[Override]
    public function unit(): string
    {
        return 'characters';
    }

    /**
     * The characters of a text, its white space at both ends — no-break space included — left out.
     *
     * @internal
     */
    public static function visibleLength(string $text): int
    {
        return mb_strlen((string) preg_replace('/^[\s\p{Z}]+|[\s\p{Z}]+$/u', '', $text));
    }
}

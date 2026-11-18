<?php

declare(strict_types=1);

namespace Phil\Markup\Reader;

use Override;
use Phil\Markup\Exception\InputTooLarge;
use Phil\Markup\Exception\InvalidSource;
use Phil\Ujo\Exception\UjoException;
use Phil\Ujo\Json\JsonDecoder;
use Phil\Ujo\Ujo;

/**
 * UJO (Universal JSON Output), the block document format, read strictly by `phil/ujo` into a checked, canonical
 * document.
 *
 * Its ceiling is that of `phil/ujo`'s `JsonDecoder`, 4 MiB, rather than the 1 MiB of the readers of written formats: a
 * document stored as UJO is the output of the other readers and of block editors, whose JSON weighs more than the text
 * it holds.
 */
final readonly class UjoReader implements MarkupReader
{
    /** The ceiling of a UJO source, in bytes, of this reader and of `Adapter\DefaultCompiler` unless configured: 4 MiB. */
    public const int DEFAULT_MAX_BYTES = JsonDecoder::DEFAULT_MAX_BYTES;

    /**
     * @param int<1, max> $maxBytes the longest source read, in bytes; a longer one is refused
     */
    public function __construct(
        private Ujo $ujo = new Ujo(),
        private int $maxBytes = self::DEFAULT_MAX_BYTES,
    ) {
    }

    #[Override]
    public function read(string $source): Reading
    {
        InputTooLarge::check($source, $this->maxBytes);
        try {
            return new Reading($this->ujo->decode($source));
        } catch (UjoException $ujoException) {
            throw InvalidSource::because('ujo', $ujoException->getMessage(), $ujoException);
        }
    }
}

<?php

declare(strict_types=1);

namespace Phil\Markup\Reader;

use Override;
use Phil\Markup\Exception\InputTooLarge;
use Phil\Markup\Exception\InvalidSource;
use Phil\Ujo\Exception\UjoException;
use Phil\Ujo\Ujo;

/**
 * UJO (Universal JSON Output), the block document format, read strictly by `phil/ujo` into a checked, canonical
 * document.
 */
final readonly class UjoReader implements MarkupReader
{
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

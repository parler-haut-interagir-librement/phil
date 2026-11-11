<?php

declare(strict_types=1);

namespace Phil\Markup\Reader;

use Override;
use Phil\Markup\Exception\InvalidSource;
use Phil\Ujo\Exception\UjoException;
use Phil\Ujo\Ujo;

/**
 * UJO (Universal JSON Output), the block document format, read strictly by `phil/ujo` into a checked, canonical
 * document.
 */
final readonly class UjoReader implements MarkupReader
{
    public function __construct(
        private Ujo $ujo = new Ujo(),
    ) {
    }

    #[Override]
    public function read(string $source): Reading
    {
        try {
            return new Reading($this->ujo->decode($source));
        } catch (UjoException $ujoException) {
            throw InvalidSource::because('ujo', $ujoException->getMessage(), $ujoException);
        }
    }
}

<?php

declare(strict_types=1);

namespace Phil\Markup\Reader;

use Override;
use Phil\Markup\Exception\InputTooLarge;

/**
 * The reader of an add-on's format, given by `Adapter\DefaultCompiler` behind the compiler's ceiling: a source longer
 * than it is refused before the add-on's reader sees it.
 *
 * @internal
 */
final readonly class BoundedReader implements MarkupReader
{
    /**
     * @param int<1, max> $maxBytes
     */
    public function __construct(
        private MarkupReader $reader,
        private int $maxBytes,
    ) {
    }

    #[Override]
    public function read(string $source): Reading
    {
        InputTooLarge::check($source, $this->maxBytes);

        return $this->reader->read($source);
    }
}

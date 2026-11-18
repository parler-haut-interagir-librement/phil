<?php

declare(strict_types=1);

namespace Phil\Markup\Reader;

use Phil\Markup\Exception\InputTooLarge;
use Phil\Markup\Exception\InvalidSource;

/**
 * Reads a source in one format into a UJO document, checked and in canonical form: the step every format takes
 * before it is rendered as HTML by `phil/ujo`.
 *
 * Each reader of the library refuses a source longer than its `maxBytes`, before it parses it.
 */
interface MarkupReader
{
    /**
     * The ceiling of a source, in bytes, of each reader and of `Adapter\DefaultCompiler` unless configured: 1 MiB.
     * `UjoReader` has its own, 4 MiB.
     */
    public const int DEFAULT_MAX_BYTES = 1_048_576;

    /**
     * @throws InputTooLarge when the source is longer than the reader's ceiling
     * @throws InvalidSource when the format has rules a source can break; the readers of written formats never throw it
     */
    public function read(string $source): Reading;
}

<?php

declare(strict_types=1);

namespace Phil\Markup\Reader;

use Phil\Markup\Exception\InvalidSource;

/**
 * Reads a source in one format into a UJO document, checked and in canonical form: the step every format takes
 * before it is rendered as HTML by `phil/ujo`.
 */
interface MarkupReader
{
    /**
     * @throws InvalidSource when the format has rules a source can break; the readers of written formats never throw
     */
    public function read(string $source): Reading;
}

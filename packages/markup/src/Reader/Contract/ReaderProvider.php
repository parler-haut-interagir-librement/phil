<?php

declare(strict_types=1);

namespace Phil\Markup\Reader\Contract;

use Phil\Markup\Level;
use Phil\Markup\Reader\MarkupReader;
use Phil\Ujo\Normalizer;

/**
 * The reader of a format the library does not ship, which an add-on gives `Adapter\DefaultCompiler` under the format's
 * name.
 */
interface ReaderProvider
{
    /**
     * The reader of a source read at a level: raw HTML written in it is kept at `all` only. The document it returns is
     * normalized by `$normalizer`.
     */
    public function reader(Level $level, Normalizer $normalizer): MarkupReader;
}

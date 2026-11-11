<?php

declare(strict_types=1);

namespace Phil\Typography\Fixer;

use Phil\Typography\Text\Run;

/**
 * One typographic rule, applied to the text of a block. A fixer never adds or removes markup: it rewrites characters,
 * so what it fixes in HTML stays as safe as it was.
 */
interface Fixer
{
    public function fix(Run $run): Run;
}

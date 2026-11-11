<?php

declare(strict_types=1);

namespace Phil\Typography\Tests\Generator;

/**
 * Where a group of consonants between two vowels is broken.
 */
enum Split
{
    /**
     * Before its last consonant, or before the inseparable cluster that ends it: `temp-lom`, `ses-tra`.
     */
    case BeforeLast;

    /**
     * After its first consonant, the inseparable clusters staying whole: `in-stru-men`, `a-blou`.
     */
    case AfterFirst;
}

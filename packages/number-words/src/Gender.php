<?php

declare(strict_types=1);

namespace Phil\NumberWords;

/**
 * The grammatical gender a number agrees with: the gender of the noun it counts or ranks.
 */
enum Gender: string
{
    case Masculine = 'masculine';
    case Feminine = 'feminine';
    case Neuter = 'neuter';
}

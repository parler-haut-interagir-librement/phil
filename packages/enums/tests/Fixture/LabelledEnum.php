<?php

declare(strict_types=1);

namespace Phil\Enums\Tests\Fixture;

use Phil\Enums\Traits\AsStringSelectableEnumTrait;

/**
 * Case names in every shape the default label of `stringOptions()` reads: snake case, one upper-case word, Pascal
 * case, and names with letters outside ASCII.
 */
enum LabelledEnum: string
{
    use AsStringSelectableEnumTrait;

    case SNAKE_CASE = 'snake';
    case UPPER = 'upper';
    case PascalCase = 'pascal';
    case AéB = 'mixed';
    case ÉTÉ_DRAFT = 'summer';
}

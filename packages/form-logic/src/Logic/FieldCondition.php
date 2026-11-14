<?php

declare(strict_types=1);

namespace Phil\FormLogic\Logic;

use Phil\FormLogic\Contract\Expression;
use Phil\FormLogic\Exception\InvalidLogic;
use Phil\FormLogic\Expression\Variable;

/**
 * The condition under which a field is shown and its answer kept.
 */
final readonly class FieldCondition
{
    /**
     * @throws InvalidLogic when the field's name is not a variable name
     */
    public function __construct(public string $field, public Expression $shownWhen)
    {
        Variable::checkName($field);
    }
}

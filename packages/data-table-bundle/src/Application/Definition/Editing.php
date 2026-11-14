<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Application\Definition;

use Closure;
use Symfony\Component\Validator\Constraint;

/**
 * How a column is edited in place: the voter attribute asked on the row at every call, the constraints of the value
 * and what applies it. The value reaches `$apply` typed by its column: a string, an `int` or `float`, a
 * `DateTimeImmutable`, a `bool`, a backed enum's case or the choice's value, or null for an emptied number, date or
 * enum.
 */
final readonly class Editing
{
    /**
     * @param Closure(array<string, mixed>|object, mixed): void $apply
     * @param list<Constraint>                                  $constraints
     */
    public function __construct(
        public string $attribute,
        public Closure $apply,
        public array $constraints = [],
    ) {
    }
}

<?php

declare(strict_types=1);

namespace Phil\FormsBundle\Domain\Contract;

use Phil\FormsBundle\Domain\Model\Form;

/**
 * The places taken in the choices of a form that have a capacity: one place per entry that kept the choice, given back
 * when the entry is removed. Taking a place is atomic: two entries sent at once never both take the last one.
 */
interface ChoicePlaces
{
    /**
     * Takes a place, unless the `$capacity` places are taken.
     *
     * @return bool whether a place was taken
     */
    public function take(Form $form, string $field, string $choice, int $capacity): bool;

    /**
     * Gives a place back; nothing happens when none is taken.
     */
    public function release(Form $form, string $field, string $choice): void;

    /**
     * How many places are taken.
     */
    public function taken(Form $form, string $field, string $choice): int;
}

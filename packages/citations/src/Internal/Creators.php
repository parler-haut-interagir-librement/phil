<?php

declare(strict_types=1);

namespace Phil\Citations\Internal;

use Phil\Citations\Model\Name;
use Phil\Citations\Model\NameVariable;
use Phil\Citations\Model\Reference;

/**
 * The names a reference is sorted and told apart by: its authors, else its editors.
 *
 * @internal
 */
final class Creators
{
    /**
     * @return list<Name>
     */
    public static function of(Reference $reference): array
    {
        $authors = $reference->names(NameVariable::Author);

        return [] !== $authors ? $authors : $reference->names(NameVariable::Editor);
    }
}

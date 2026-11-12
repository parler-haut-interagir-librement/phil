<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Application;

use Phil\MediaBundle\Domain\ValueObject\MediaId;

/**
 * Where media are used: the columns that hold a medium, and how a deletion treats them.
 */
interface MediaReferences
{
    /**
     * The columns that hold this medium, each with the number of records; a column that does not is left out.
     *
     * @return list<MediaReference>
     */
    public function of(MediaId $id): array;

    /**
     * Empties the column in every record that holds this medium.
     */
    public function detach(MediaId $id, MediaReference $reference): void;
}

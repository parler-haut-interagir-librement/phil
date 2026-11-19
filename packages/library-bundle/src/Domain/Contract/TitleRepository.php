<?php

declare(strict_types=1);

namespace Phil\LibraryBundle\Domain\Contract;

use Phil\LibraryBundle\Domain\Model\Title;

/**
 * Where the titles of the catalogue are kept.
 */
interface TitleRepository
{
    public function get(string $id): ?Title;

    public function save(Title $title): void;
}

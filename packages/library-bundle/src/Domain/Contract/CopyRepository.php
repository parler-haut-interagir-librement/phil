<?php

declare(strict_types=1);

namespace Phil\LibraryBundle\Domain\Contract;

use Phil\LibraryBundle\Domain\Model\Copy;
use Phil\LibraryBundle\Domain\Model\Title;

/**
 * Where the copies are kept.
 */
interface CopyRepository
{
    public function get(string $id): ?Copy;

    public function byBarcode(string $barcode): ?Copy;

    /**
     * The copies of a title, in the order of their barcodes, at most so many.
     *
     * @return list<Copy>
     */
    public function ofTitle(Title $title, int $limit): array;

    public function save(Copy $copy): void;
}

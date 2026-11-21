<?php

declare(strict_types=1);

namespace Phil\ImportWizard\Import;

use Phil\ImportWizard\Plan\RowRejection;

/**
 * What an import wrote: the number of records created and updated, the batches written, and every row refused — by
 * the dry run, by the target, or because a row it refers to was refused —, in the order of the file.
 */
final readonly class ImportResult
{
    /**
     * @param list<RowRejection> $rejections
     */
    public function __construct(
        public int $created,
        public int $updated,
        public int $batches,
        public array $rejections,
    ) {
    }
}

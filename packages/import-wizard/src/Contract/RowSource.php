<?php

declare(strict_types=1);

namespace Phil\ImportWizard\Contract;

use Phil\ImportWizard\Source\SourceRow;

/**
 * The rows of a file: its header, then its records with their physical line. `CsvRowSource` reads CSV; an adapter
 * reads any other format the same way.
 */
interface RowSource
{
    /**
     * The cells of the header, trimmed.
     *
     * @return list<string>
     */
    public function headers(): array;

    /**
     * The records after the header, blank ones included, in the order of the file.
     *
     * @return iterable<SourceRow>
     */
    public function rows(): iterable;
}

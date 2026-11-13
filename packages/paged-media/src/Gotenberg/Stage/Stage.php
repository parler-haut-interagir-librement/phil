<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Gotenberg\Stage;

use Phil\PagedMedia\Gotenberg\InputFile;

/**
 * One operation on a PDF: in a list on `/forms/pdf/pipe` or after a conversion, or alone on its own route. A stage
 * checks its options when it is built, so a request Gotenberg would refuse is never sent.
 */
interface Stage
{
    public function operation(): Operation;

    /**
     * The options as Gotenberg names them, without the defaults left unset; a file option holds the file's name.
     *
     * @return array<string, scalar|array<array-key, mixed>>
     */
    public function options(): array;

    /**
     * The files the options name, uploaded beside the PDFs; Gotenberg does not process them as PDFs.
     *
     * @return list<InputFile>
     */
    public function files(): array;
}

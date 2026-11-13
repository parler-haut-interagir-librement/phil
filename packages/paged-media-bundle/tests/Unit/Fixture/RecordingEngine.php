<?php

declare(strict_types=1);

namespace Phil\PagedMediaBundle\Tests\Unit\Fixture;

use Override;
use Phil\PagedMediaBundle\Application\Contract\PrintEngine;
use Phil\PagedMediaBundle\Application\PrintableHtml;

/**
 * A print engine that keeps the pages it is given and answers the same bytes.
 */
final class RecordingEngine implements PrintEngine
{
    /** @var list<PrintableHtml> */
    public array $pages = [];

    public function __construct(
        private readonly string $pdf,
    ) {
    }

    #[Override]
    public function pdf(PrintableHtml $page): string
    {
        $this->pages[] = $page;

        return $this->pdf;
    }
}

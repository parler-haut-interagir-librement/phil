<?php

declare(strict_types=1);

namespace Phil\Slides\Tests\Functional\App;

use Override;
use Phil\PagedMediaBundle\Application\Contract\PrintEngine;
use Phil\PagedMediaBundle\Application\PrintableHtml;

/**
 * A print engine that hands the document back instead of laying it out, so that the tests read what it was given.
 */
final class HtmlPrintEngine implements PrintEngine
{
    #[Override]
    public function pdf(PrintableHtml $page): string
    {
        return $page->document();
    }
}

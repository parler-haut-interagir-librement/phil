<?php

declare(strict_types=1);

namespace Phil\PagedMediaBundle\Tests\Functional\App;

use Override;
use Phil\PagedMedia\Pdf\Document;
use Phil\PagedMedia\Pdf\PageSize;
use Phil\PagedMediaBundle\Application\AsDocumentTemplate;
use Phil\PagedMediaBundle\Application\DocumentTemplate;

/**
 * The application's ticket, registered by its attribute.
 */
#[AsDocumentTemplate('ticket')]
final readonly class Ticket implements DocumentTemplate
{
    #[Override]
    public function build(array $parameters): Document
    {
        $document = new Document(PageSize::a5());
        $document->addPage();
        $document->text(36, 500, 'Admit one', 14);

        return $document;
    }
}

<?php

declare(strict_types=1);

namespace Phil\PagedMediaBundle\Tests\Unit\Fixture;

use Override;
use Phil\PagedMedia\Pdf\Document;
use Phil\PagedMedia\Pdf\Metadata;
use Phil\PagedMedia\Pdf\PageSize;
use Phil\PagedMediaBundle\Application\AsDocumentTemplate;
use Phil\PagedMediaBundle\Application\DocumentTemplate;

/**
 * A one-page ticket that prints the `seat` parameter.
 */
#[AsDocumentTemplate('ticket')]
final readonly class Ticket implements DocumentTemplate
{
    #[Override]
    public function build(array $parameters): Document
    {
        $seat = $parameters['seat'] ?? '';
        $document = new Document(PageSize::a5(), new Metadata(title: 'Ticket'), false);
        $document->addPage();
        $document->text(36, 500, 'Seat '.(\is_string($seat) ? $seat : ''), 14);

        return $document;
    }
}

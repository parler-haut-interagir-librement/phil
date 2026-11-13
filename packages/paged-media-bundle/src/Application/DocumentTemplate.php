<?php

declare(strict_types=1);

namespace Phil\PagedMediaBundle\Application;

use Phil\PagedMedia\Pdf\Document;

/**
 * A document the application draws with the native writer from parameters: a receipt, a ticket, a certificate. It is
 * known by the name its `#[AsDocumentTemplate]` attribute gives, which a `RenderPdf` message names.
 */
interface DocumentTemplate
{
    /**
     * @param array<string, mixed> $parameters scalars and arrays, as a message carries them
     */
    public function build(array $parameters): Document;
}

<?php

declare(strict_types=1);

namespace Phil\SvgBlocksBundle\Application\Contract;

use Phil\Ujo\Document;

/**
 * Where the application keeps its documents, for the background pass that draws the diagrams of a saved document:
 * the pass reads the document by its subject, draws what is pending, and saves it back. A subject is the
 * application's own identifier — `article:42`, a UUID —, the only thing the message carries.
 */
interface MarkupDocuments
{
    /**
     * The document of a subject as it is now; `null` when it is gone.
     */
    public function find(string $subject): ?Document;

    /**
     * Keeps the document of a subject with its new drawings. The application decides what to do when the document
     * changed since `find()`: the drawings of an older text are dropped at the next save anyway.
     */
    public function save(string $subject, Document $document): void;
}

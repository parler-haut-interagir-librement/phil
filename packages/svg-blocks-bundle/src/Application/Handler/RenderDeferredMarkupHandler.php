<?php

declare(strict_types=1);

namespace Phil\SvgBlocksBundle\Application\Handler;

use Phil\SvgBlocks\BlockMarkup;
use Phil\SvgBlocksBundle\Application\Contract\MarkupDocuments;
use Phil\SvgBlocksBundle\Application\Message\RenderDeferredMarkup;
use Phil\Ujo\Document;

/**
 * Draws every block of a document that waits for its drawing, the deferred types included, and saves the document
 * when a drawing was added or dropped. A document gone since the message was sent is left alone; a diagram the
 * service refuses keeps no drawing and shows its code.
 */
final readonly class RenderDeferredMarkupHandler
{
    public function __construct(
        private MarkupDocuments $documents,
        private BlockMarkup $markup,
    ) {
    }

    public function __invoke(RenderDeferredMarkup $message): void
    {
        $document = $this->documents->find($message->subject);

        if (!$document instanceof Document) {
            return;
        }

        $rendered = $this->markup->render($document, true);

        if ($rendered->changed) {
            $this->documents->save($message->subject, $rendered->document);
        }
    }
}

<?php

declare(strict_types=1);

namespace Phil\ReceiptEmail\Application\Port;

use Phil\ReceiptEmail\Application\ReceiptMessage;
use Phil\ReceiptEmail\Application\RenderedReceipt;

/**
 * Renders a receipt's message — its template key, locale and variables — into a subject, a preheader, an HTML part, a
 * plain-text part and the images it embeds. `Infrastructure\EmailTemplates\EmailTemplatesRenderer` renders it with
 * `phil/email-templates-bundle`.
 */
interface ReceiptRenderer
{
    public function render(ReceiptMessage $message): RenderedReceipt;
}

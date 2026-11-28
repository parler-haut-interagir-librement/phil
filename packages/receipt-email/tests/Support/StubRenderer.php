<?php

declare(strict_types=1);

namespace Phil\ReceiptEmail\Tests\Support;

use Phil\ReceiptEmail\Application\Port\ReceiptRenderer;
use Phil\ReceiptEmail\Application\ReceiptMessage;
use Phil\ReceiptEmail\Application\RenderedReceipt;

/**
 * Renders every message as the receipt given, and keeps the messages it was given.
 */
final class StubRenderer implements ReceiptRenderer
{
    /** @var list<ReceiptMessage> */
    public array $messages = [];

    public function __construct(
        private readonly RenderedReceipt $rendered = new RenderedReceipt('Receipt R-1', 'Thank you', '<p>Paid</p>', 'Paid'),
    ) {
    }

    public function render(ReceiptMessage $message): RenderedReceipt
    {
        $this->messages[] = $message;

        return $this->rendered;
    }
}

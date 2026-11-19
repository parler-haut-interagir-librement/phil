<?php

declare(strict_types=1);

namespace Phil\CommerceProductTemplateBundle\Application\Message;

/**
 * Asks a worker to produce the print files and the production order of an order. It carries the order's number only:
 * the handler reads the order, its lines and what the buyers typed from their stores, and writes what it composes to
 * the production store, so the message stays short and holds no personal data.
 */
final readonly class ProducePrintFiles
{
    public function __construct(
        public string $orderNumber,
    ) {
    }
}

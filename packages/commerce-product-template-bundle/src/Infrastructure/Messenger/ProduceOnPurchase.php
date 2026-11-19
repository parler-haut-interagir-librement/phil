<?php

declare(strict_types=1);

namespace Phil\CommerceProductTemplateBundle\Infrastructure\Messenger;

use Phil\CommerceBundle\Domain\Event\OrderPurchased;
use Phil\CommerceProductTemplateBundle\Application\Message\ProducePrintFiles;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Asks for the production of an order once it is paid in full, with `production.on_purchase`: the message carries the
 * order's number only. A worker that handles it before the order's draws are made retries it.
 */
final readonly class ProduceOnPurchase
{
    public function __construct(
        private MessageBusInterface $bus,
    ) {
    }

    public function __invoke(OrderPurchased $purchased): void
    {
        $this->bus->dispatch(new ProducePrintFiles($purchased->orderNumber));
    }
}

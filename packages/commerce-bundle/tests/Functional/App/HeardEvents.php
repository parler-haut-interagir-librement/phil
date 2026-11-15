<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Tests\Functional\App;

use Phil\CommerceBundle\Domain\Event\StockCommitted;
use ReflectionClass;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Hears the stock committed by deferred sales, as a logistics module would listen.
 */
#[AsEventListener(event: StockCommitted::class)]
final class HeardEvents
{
    /** @var list<string> each event's short class name and order number */
    public array $heard = [];

    public function __invoke(StockCommitted $event): void
    {
        $this->heard[] = new ReflectionClass($event)->getShortName().' '.$event->orderNumber;
    }
}

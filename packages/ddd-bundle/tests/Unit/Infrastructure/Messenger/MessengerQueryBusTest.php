<?php

declare(strict_types=1);

namespace Phil\DddBundle\Tests\Unit\Infrastructure\Messenger;

use Phil\DddBundle\Infrastructure\Messenger\MessengerQueryBus;
use Phil\DddBundle\Tests\Unit\Infrastructure\Messenger\Fixture\ExampleDomainError;
use Phil\DddBundle\Tests\Unit\Infrastructure\Messenger\Fixture\ExampleQuery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;

#[CoversClass(MessengerQueryBus::class)]
final class MessengerQueryBusTest extends TestCase
{
    public function testItReturnsWhatTheHandlerReturns(): void
    {
        $bus = $this->busHandling(static fn (ExampleQuery $query): string => 'answer');

        self::assertSame('answer', $bus->ask(new ExampleQuery()));
    }

    /**
     * Whatever the handler throws comes out as is: a query has no transaction to protect, and the caller
     * does not need to know the bus to catch a "not found".
     */
    public function testTheHandlerFailureComesOutUnwrapped(): void
    {
        $error = new ExampleDomainError('not found');
        $bus = $this->busHandling(static function () use ($error): never {
            throw $error;
        });

        $this->expectExceptionObject($error);

        $bus->ask(new ExampleQuery());
    }

    /**
     * Unlike the command bus, the query bus unwraps more than a `DomainError`: any failure comes out as is,
     * never inside a `HandlerFailedException`.
     */
    public function testAnyOtherHandlerFailureComesOutUnwrappedToo(): void
    {
        $error = new RuntimeException('connection lost');
        $bus = $this->busHandling(static function () use ($error): never {
            throw $error;
        });

        $this->expectExceptionObject($error);

        $bus->ask(new ExampleQuery());
    }

    private function busHandling(callable $handler): MessengerQueryBus
    {
        return new MessengerQueryBus(new MessageBus([
            new HandleMessageMiddleware(new HandlersLocator([ExampleQuery::class => [$handler]])),
        ]));
    }
}

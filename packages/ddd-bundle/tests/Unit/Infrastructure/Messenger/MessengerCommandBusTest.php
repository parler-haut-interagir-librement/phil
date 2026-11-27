<?php

declare(strict_types=1);

namespace Phil\DddBundle\Tests\Unit\Infrastructure\Messenger;

use Phil\DddBundle\Infrastructure\Messenger\MessengerCommandBus;
use Phil\DddBundle\Tests\Unit\Infrastructure\Messenger\Fixture\ExampleCommand;
use Phil\DddBundle\Tests\Unit\Infrastructure\Messenger\Fixture\ExampleDomainError;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Handler\HandlerDescriptor;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;

#[CoversClass(MessengerCommandBus::class)]
final class MessengerCommandBusTest extends TestCase
{
    public function testItDispatchesTheCommandToItsHandler(): void
    {
        $handled = [];
        $bus = $this->busHandling([
            static function (ExampleCommand $command) use (&$handled): void {
                $handled[] = $command;
            },
        ]);

        $command = new ExampleCommand();
        $bus->dispatch($command);

        self::assertSame([$command], $handled);
    }

    /**
     * The caller catches `DomainError`, not `HandlerFailedException`: it does not need to know the bus.
     */
    public function testADomainErrorComesOutUnwrapped(): void
    {
        $error = new ExampleDomainError('refused');
        $bus = $this->busHandling([
            static function () use ($error): never {
                throw $error;
            },
        ]);

        $this->expectExceptionObject($error);

        $bus->dispatch(new ExampleCommand());
    }

    /**
     * A failure that is not a domain error stays wrapped: unwrapping it would pass it off as an invariant
     * refusal, and the caller would treat it as one.
     */
    public function testAnyOtherFailureStaysWrapped(): void
    {
        $bus = $this->busHandling([
            static function (): never {
                throw new RuntimeException('failure');
            },
        ]);

        $this->expectException(HandlerFailedException::class);

        $bus->dispatch(new ExampleCommand());
    }

    /**
     * When several handlers fail, the domain error comes out even if it is not the first.
     */
    public function testTheDomainErrorIsFoundAmongSeveralFailures(): void
    {
        $error = new ExampleDomainError('refused');
        // Two closures carry the same name for Messenger, which then runs only the first: the alias tells
        // them apart.
        $bus = $this->busHandling([
            new HandlerDescriptor(static function (): never {
                throw new RuntimeException('failure');
            }, ['alias' => 'failure']),
            new HandlerDescriptor(static function () use ($error): never {
                throw $error;
            }, ['alias' => 'refusal']),
        ]);

        $this->expectExceptionObject($error);

        $bus->dispatch(new ExampleCommand());
    }

    /**
     * A bus configured with `unwrap_exceptions` throws the handler's exception itself: the domain error still comes
     * out as is.
     */
    public function testOnABusThatUnwrapsTheDomainErrorComesOutAsIs(): void
    {
        $error = new ExampleDomainError('refused');
        $bus = $this->busHandling([
            static function () use ($error): never {
                throw $error;
            },
        ], unwrapExceptions: true);

        $this->expectExceptionObject($error);

        $bus->dispatch(new ExampleCommand());
    }

    /**
     * On a bus configured with `unwrap_exceptions`, any other failure reaches the caller as the bus gives it: unwrapped,
     * as the application asked.
     */
    public function testOnABusThatUnwrapsAnyOtherFailureComesOutAsTheBusGivesIt(): void
    {
        $error = new RuntimeException('failure');
        $bus = $this->busHandling([
            static function () use ($error): never {
                throw $error;
            },
        ], unwrapExceptions: true);

        $this->expectExceptionObject($error);

        $bus->dispatch(new ExampleCommand());
    }

    /**
     * @param list<callable|HandlerDescriptor> $handlers
     */
    private function busHandling(array $handlers, bool $unwrapExceptions = false): MessengerCommandBus
    {
        return new MessengerCommandBus(new MessageBus([
            new HandleMessageMiddleware(new HandlersLocator([ExampleCommand::class => $handlers])),
        ], unwrapExceptions: $unwrapExceptions));
    }
}

<?php

declare(strict_types=1);

namespace Phil\ApiContractBundle\Tests\Unit\Infrastructure;

use LogicException;
use Phil\ApiContractBundle\Infrastructure\DependencyInjection\AddTenantIdempotencyScopeMiddleware;
use Phil\ApiContractBundle\Infrastructure\Messenger\TenantIdempotencyScopeMiddleware;
use Phil\Idempotency\Messenger\IdempotencyMiddleware;
use Phil\TenancyBundle\Infrastructure\Messenger\TenantMiddleware;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Messenger\DependencyInjection\MessengerPass;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\SendMessageMiddleware;

/**
 * The tenant scope of messages on the buses: placed before the idempotency middleware by Messenger, on the buses the
 * configuration names or on those that hold the idempotency middleware, and never twice.
 */
#[CoversClass(AddTenantIdempotencyScopeMiddleware::class)]
final class AddTenantIdempotencyScopeMiddlewareTest extends TestCase
{
    private const string SCOPE = TenantIdempotencyScopeMiddleware::class;

    public function testByDefaultTheBusesOfTheIdempotencyMiddlewareReceiveItBeforeIt(): void
    {
        $container = $this->container([
            'messenger.bus.default' => [TenantMiddleware::class, IdempotencyMiddleware::class, 'send_message'],
            'command.bus' => [IdempotencyMiddleware::class, TenantMiddleware::class],
            'event.bus' => [TenantMiddleware::class, 'send_message'],
        ]);

        $this->compile($container);

        self::assertSame([TenantMiddleware::class, self::SCOPE, IdempotencyMiddleware::class, 'messenger.middleware.send_message'], $this->middleware($container, 'messenger.bus.default'));
        self::assertSame([TenantMiddleware::class, self::SCOPE, IdempotencyMiddleware::class], $this->middleware($container, 'command.bus'), 'Messenger meets both constraints when the configuration lists them in the other order.');
        self::assertSame([TenantMiddleware::class, 'messenger.middleware.send_message'], $this->middleware($container, 'event.bus'), 'A bus without the idempotency middleware is left alone.');
    }

    public function testTheBusesTheConfigurationNamesReceiveItAlone(): void
    {
        $container = $this->container([
            'messenger.bus.default' => [IdempotencyMiddleware::class],
            'event.bus' => [TenantMiddleware::class, 'send_message'],
        ], ['event.bus']);

        $this->compile($container);

        self::assertSame([IdempotencyMiddleware::class], $this->middleware($container, 'messenger.bus.default'));
        self::assertSame([TenantMiddleware::class, self::SCOPE, 'messenger.middleware.send_message'], $this->middleware($container, 'event.bus'), 'In front of the middleware that sends.');
        self::assertStringStartsWith('.', AddTenantIdempotencyScopeMiddleware::BUSES, 'A build parameter, gone from the compiled container.');
    }

    public function testABusThatAlreadyListsItKeepsItOnce(): void
    {
        $container = $this->container([
            'messenger.bus.default' => [IdempotencyMiddleware::class, self::SCOPE],
            'command.bus' => ['scope', IdempotencyMiddleware::class],
            'query.bus' => ['scope_alias', IdempotencyMiddleware::class],
        ]);
        $container->register('messenger.middleware.scope', self::SCOPE);
        $container->setAlias('scope_alias', self::SCOPE);

        $this->compile($container);

        self::assertSame([IdempotencyMiddleware::class, self::SCOPE], $this->middleware($container, 'messenger.bus.default'), 'Where the configuration puts it.');
        self::assertSame(['messenger.middleware.scope', IdempotencyMiddleware::class], $this->middleware($container, 'command.bus'), 'Named as Messenger names its own middleware.');
        self::assertSame(['scope_alias', IdempotencyMiddleware::class], $this->middleware($container, 'query.bus'), 'Through an alias.');
        self::assertSame([], $container->getDefinition(self::SCOPE)->getTag(AddTenantIdempotencyScopeMiddleware::MIDDLEWARE_TAG));
    }

    public function testABusForWhichItIsAlreadyTaggedKeepsItsTag(): void
    {
        $container = $this->container(['messenger.bus.default' => [IdempotencyMiddleware::class, 'send_message']]);
        $container->getDefinition(self::SCOPE)->addTag(AddTenantIdempotencyScopeMiddleware::MIDDLEWARE_TAG, ['bus' => 'messenger.bus.default']);

        $this->compile($container);

        self::assertSame([['bus' => 'messenger.bus.default']], $container->getDefinition(self::SCOPE)->getTag(AddTenantIdempotencyScopeMiddleware::MIDDLEWARE_TAG));
        self::assertSame([IdempotencyMiddleware::class, self::SCOPE, 'messenger.middleware.send_message'], $this->middleware($container, 'messenger.bus.default'));
    }

    public function testABusThatIsNotWantedDoesNotStopTheOnesThatFollow(): void
    {
        $container = $this->container([
            'event.bus' => [TenantMiddleware::class],
            'with.scope' => [IdempotencyMiddleware::class, self::SCOPE],
            'command.bus' => [IdempotencyMiddleware::class],
        ]);

        $this->compile($container);

        self::assertSame([TenantMiddleware::class], $this->middleware($container, 'event.bus'));
        self::assertSame([IdempotencyMiddleware::class, self::SCOPE], $this->middleware($container, 'with.scope'));
        self::assertSame([self::SCOPE, IdempotencyMiddleware::class], $this->middleware($container, 'command.bus'), 'A bus that precedes it, unwanted or served already, does not end the pass.');
    }

    public function testABusWhoseMiddlewareIsNotAListDoesNotStopTheOnesThatFollow(): void
    {
        $container = new ContainerBuilder();
        $container->register(self::SCOPE, self::SCOPE);
        $container->register(IdempotencyMiddleware::class, IdempotencyMiddleware::class);
        $container->setParameter(AddTenantIdempotencyScopeMiddleware::BUSES, []);
        $container->register('broken.bus', MessageBus::class)->addTag('messenger.bus');
        $container->setParameter('broken.bus.middleware', 'not a list');
        $container->register('command.bus', MessageBus::class)->addTag('messenger.bus');
        $container->setParameter('command.bus.middleware', [['id' => IdempotencyMiddleware::class]]);

        new AddTenantIdempotencyScopeMiddleware()->process($container);

        self::assertSame([['bus' => 'command.bus', 'before' => [IdempotencyMiddleware::class], 'after' => [TenantMiddleware::class]]], $container->getDefinition(self::SCOPE)->getTag(AddTenantIdempotencyScopeMiddleware::MIDDLEWARE_TAG));
    }

    public function testOnlyTheNamesOfTheConfigurationAreBuses(): void
    {
        $container = $this->container(['event.bus' => [TenantMiddleware::class, 'send_message']]);
        $container->setParameter(AddTenantIdempotencyScopeMiddleware::BUSES, [42, null, 'event.bus']);

        $this->compile($container);

        self::assertSame([TenantMiddleware::class, self::SCOPE, 'messenger.middleware.send_message'], $this->middleware($container, 'event.bus'), 'A value that is not a name is left out, not looked up as a bus.');
    }

    public function testABusTheConfigurationNamesMustExist(): void
    {
        $container = $this->container(['messenger.bus.default' => [IdempotencyMiddleware::class], 'event.bus' => []], ['messenger.bus.default', 'command.bus']);
        $container->register('no.middleware.bus', MessageBus::class)->addTag('messenger.bus');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('The bus "command.bus" that phil_api_contract.idempotency.messenger.buses names is not a Messenger bus of the application; its buses are "messenger.bus.default", "event.bus".');

        new AddTenantIdempotencyScopeMiddleware()->process($container);
    }

    public function testWithoutTheOptionNothingIsAdded(): void
    {
        $container = $this->container(['messenger.bus.default' => [IdempotencyMiddleware::class]], ['messenger.bus.default']);
        $container->removeDefinition(self::SCOPE);

        $this->compile($container);

        self::assertSame([IdempotencyMiddleware::class], $this->middleware($container, 'messenger.bus.default'));
    }

    public function testMiddlewareThatIsNotAServiceOrHasNoClassIsNamedByItsIdentifier(): void
    {
        $container = $this->container(['messenger.bus.default' => [IdempotencyMiddleware::class]]);
        $container->setParameter('messenger.bus.default.middleware', [['id' => IdempotencyMiddleware::class], ['id' => 'unknown'], ['arguments' => []], 'not an item', ['id' => 'factory']]);
        $container->register('factory');
        $container->register('broken.bus', MessageBus::class)->addTag('messenger.bus');
        $container->setParameter('broken.bus.middleware', 'not a list');

        new AddTenantIdempotencyScopeMiddleware()->process($container);

        self::assertSame([['bus' => 'messenger.bus.default', 'before' => [IdempotencyMiddleware::class], 'after' => [TenantMiddleware::class]]], $container->getDefinition(self::SCOPE)->getTag(AddTenantIdempotencyScopeMiddleware::MIDDLEWARE_TAG));
    }

    /**
     * A container with the buses, each with its middleware listed as the framework's configuration lists it, the
     * scope's service as the bundle registers it, and the buses the configuration names.
     *
     * @param array<string, list<string>> $buses
     * @param list<string>                $named
     */
    private function container(array $buses, array $named = []): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->register(self::SCOPE, self::SCOPE);
        $container->register(TenantMiddleware::class, TenantMiddleware::class);
        $container->register(IdempotencyMiddleware::class, IdempotencyMiddleware::class);
        $container->register('messenger.middleware.send_message', SendMessageMiddleware::class);
        $container->setParameter(AddTenantIdempotencyScopeMiddleware::BUSES, $named);

        foreach ($buses as $bus => $middleware) {
            $container->register($bus, MessageBus::class)->setArguments([[]])->addTag('messenger.bus');
            $container->setParameter($bus.'.middleware', array_map(static fn (string $id): array => ['id' => $id], $middleware));
        }

        return $container;
    }

    private function compile(ContainerBuilder $container): void
    {
        new AddTenantIdempotencyScopeMiddleware()->process($container);
        new MessengerPass()->process($container);
    }

    /**
     * @return list<string>
     */
    private function middleware(ContainerBuilder $container, string $bus): array
    {
        $argument = $container->getDefinition($bus)->getArgument(0);
        self::assertInstanceOf(IteratorArgument::class, $argument);

        return array_values(array_map(static fn (mixed $reference): string => $reference instanceof Reference ? (string) $reference : '', $argument->getValues()));
    }
}

<?php

declare(strict_types=1);

namespace Phil\DddBundle\Tests\Unit;

use Phil\DddBundle\Application\Bus\CommandBus;
use Phil\DddBundle\Application\Bus\CommandHandler;
use Phil\DddBundle\Application\Bus\QueryBus;
use Phil\DddBundle\Application\Bus\QueryHandler;
use Phil\DddBundle\Infrastructure\Messenger\MessengerCommandBus;
use Phil\DddBundle\Infrastructure\Messenger\MessengerQueryBus;
use Phil\DddBundle\PhilDddBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * The wiring the bundle declares, read from an uncompiled `ContainerBuilder`: no kernel, no real Messenger.
 * That is what keeps this test in the unit suite, and therefore under mutation testing.
 */
#[CoversClass(PhilDddBundle::class)]
final class PhilDddBundleTest extends TestCase
{
    public function testTheDefaultsPointAtTheConventionalBusIds(): void
    {
        $container = $this->load([]);

        $this->assertBusArgument($container, MessengerCommandBus::class, 'command.bus');
        $this->assertBusArgument($container, MessengerQueryBus::class, 'query.bus');
        $this->assertHandlerTag($container, CommandHandler::class, 'command.bus');
        $this->assertHandlerTag($container, QueryHandler::class, 'query.bus');
    }

    public function testTheBusIdsAreConfigurable(): void
    {
        $container = $this->load(['command_bus' => 'write.bus', 'query_bus' => 'read.bus']);

        $this->assertBusArgument($container, MessengerCommandBus::class, 'write.bus');
        $this->assertBusArgument($container, MessengerQueryBus::class, 'read.bus');
        $this->assertHandlerTag($container, CommandHandler::class, 'write.bus');
        $this->assertHandlerTag($container, QueryHandler::class, 'read.bus');
    }

    public function testThePortsAreAliasedToTheirAdapters(): void
    {
        $container = $this->load([]);

        self::assertSame(MessengerCommandBus::class, (string) $container->getAlias(CommandBus::class));
        self::assertSame(MessengerQueryBus::class, (string) $container->getAlias(QueryBus::class));
    }

    /**
     * A scalar node accepts an integer; the bundle refuses it rather than wiring the service "5".
     */
    public function testANonStringQueryBusIdIsRefused(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('"phil_ddd.query_bus" must be a service id.');

        $this->load(['query_bus' => 5]);
    }

    public function testANonStringCommandBusIdIsRefused(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('"phil_ddd.command_bus" must be a service id.');

        $this->load(['command_bus' => 5]);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function busKeys(): iterable
    {
        yield 'command bus' => ['command_bus'];
        yield 'query bus' => ['query_bus'];
    }

    #[DataProvider('busKeys')]
    public function testAnEmptyBusIdIsRefused(string $key): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('phil_ddd.%s', $key));

        $this->load([$key => '']);
    }

    /**
     * @param array<string, int|string> $config
     */
    private function load(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $bundle = new PhilDddBundle();
        $extension = $bundle->getContainerExtension();
        self::assertNotNull($extension);
        self::assertSame('phil_ddd', $extension->getAlias());

        $extension->load([$config], $container);

        return $container;
    }

    private function assertBusArgument(ContainerBuilder $container, string $service, string $bus): void
    {
        $argument = $container->getDefinition($service)->getArgument(0);

        self::assertInstanceOf(Reference::class, $argument);
        self::assertSame($bus, (string) $argument);
    }

    /**
     * @param class-string $marker
     */
    private function assertHandlerTag(ContainerBuilder $container, string $marker, string $bus): void
    {
        $conditionals = $container->getAutoconfiguredInstanceof();

        self::assertArrayHasKey($marker, $conditionals);
        self::assertInstanceOf(Definition::class, $conditionals[$marker]);
        self::assertSame([['bus' => $bus]], $conditionals[$marker]->getTag('messenger.message_handler'));
    }
}

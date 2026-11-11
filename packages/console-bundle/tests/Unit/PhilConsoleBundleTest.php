<?php

declare(strict_types=1);

namespace Phil\ConsoleBundle\Tests\Unit;

use Phil\ConsoleBundle\Application\EnvironmentGuard;
use Phil\ConsoleBundle\Domain\SummaryMode;
use Phil\ConsoleBundle\Domain\TimestampFormat;
use Phil\ConsoleBundle\Domain\ValueObject\ConsoleSettings;
use Phil\ConsoleBundle\Infrastructure\Command\LintGuardsCommand;
use Phil\ConsoleBundle\Infrastructure\Console\CommandGuardReader;
use Phil\ConsoleBundle\Infrastructure\Console\ConsoleOptions;
use Phil\ConsoleBundle\Infrastructure\Console\StreamStamps;
use Phil\ConsoleBundle\Infrastructure\DependencyInjection\ConsoleServices;
use Phil\ConsoleBundle\Infrastructure\DependencyInjection\LoggerChannelsPass;
use Phil\ConsoleBundle\Infrastructure\EventListener\ConsoleListener;
use Phil\ConsoleBundle\Infrastructure\Monolog\ChannelDebugSwitch;
use Phil\ConsoleBundle\Infrastructure\Runtime\PhpMemoryGauge;
use Phil\ConsoleBundle\Infrastructure\Runtime\SystemClock;
use Phil\ConsoleBundle\PhilConsoleBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Argument\ServiceLocatorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(PhilConsoleBundle::class)]
#[CoversClass(ConsoleServices::class)]
final class PhilConsoleBundleTest extends TestCase
{
    public function testTheDefaults(): void
    {
        $container = $this->load([]);

        self::assertSame('phil_console', $this->extension()->getAlias());
        self::assertSame(
            ['service_container', ConsoleServices::CLOCK, ConsoleServices::MEMORY_GAUGE, CommandGuardReader::class, ConsoleOptions::class, EnvironmentGuard::class, StreamStamps::class, ConsoleListener::class, LintGuardsCommand::class, ConsoleServices::DEBUG_SWITCH],
            array_keys($container->getDefinitions()),
        );
        self::assertSame(SystemClock::class, $container->getDefinition(ConsoleServices::CLOCK)->getClass());
        self::assertSame(PhpMemoryGauge::class, $container->getDefinition(ConsoleServices::MEMORY_GAUGE)->getClass());
        self::assertSame(CommandGuardReader::class, $container->getDefinition(CommandGuardReader::class)->getClass());
        self::assertSame(ConsoleOptions::class, $container->getDefinition(ConsoleOptions::class)->getClass());
        self::assertSame(['%kernel.environment%'], $container->getDefinition(EnvironmentGuard::class)->getArguments());
        self::assertSame(EnvironmentGuard::class, $container->getDefinition(EnvironmentGuard::class)->getClass());
        self::assertEquals([new Reference(ConsoleServices::CLOCK)], $container->getDefinition(StreamStamps::class)->getArguments());
        self::assertSame(StreamStamps::class, $container->getDefinition(StreamStamps::class)->getClass());

        $listener = $container->getDefinition(ConsoleListener::class);
        self::assertSame(ConsoleListener::class, $listener->getClass());
        self::assertEquals([
            new Reference(ConsoleOptions::class),
            new Reference(CommandGuardReader::class),
            new Reference(EnvironmentGuard::class),
            new Reference(StreamStamps::class),
            new Reference(ConsoleServices::MEMORY_GAUGE),
            new Reference(ConsoleServices::CLOCK),
            new Definition(ConsoleSettings::class, [false, TimestampFormat::Iso, SummaryMode::Verbose, []]),
            new Reference(ConsoleServices::DEBUG_SWITCH, ContainerInterface::IGNORE_ON_INVALID_REFERENCE),
        ], $listener->getArguments());
        self::assertSame(['kernel.event_listener' => [['event' => 'console.command', 'method' => 'onCommand'], ['event' => 'console.terminate', 'method' => 'onTerminate']], 'kernel.reset' => [['method' => 'reset']]], $listener->getTags());

        $lint = $container->getDefinition(LintGuardsCommand::class);
        self::assertSame(LintGuardsCommand::class, $lint->getClass());
        self::assertEquals([new Reference(CommandGuardReader::class)], $lint->getArguments());
        self::assertSame(['console.command' => [['command' => 'lint:console-guards']]], $lint->getTags());

        $switch = $container->getDefinition(ConsoleServices::DEBUG_SWITCH);
        self::assertSame(ChannelDebugSwitch::class, $switch->getClass());
        self::assertEquals([new ServiceLocatorArgument([]), []], $switch->getArguments());
        self::assertSame(['kernel.reset' => [['method' => 'reset']]], $switch->getTags());
    }

    public function testTheConfiguration(): void
    {
        $container = $this->load([
            'timestamps' => ['enabled' => true, 'format' => 'relative'],
            'summary' => 'always',
            'debug' => ['channels' => ['doctrine', 'app']],
        ]);

        self::assertEquals(
            new Definition(ConsoleSettings::class, [true, TimestampFormat::Relative, SummaryMode::Always, ['doctrine', 'app']]),
            $container->getDefinition(ConsoleListener::class)->getArgument(6),
        );
    }

    public function testTheDebugOptionCanBeDisabled(): void
    {
        self::assertFalse($this->load(['debug' => false])->hasDefinition(ConsoleServices::DEBUG_SWITCH));
        self::assertFalse($this->load(['debug' => ['enabled' => false]])->hasDefinition(ConsoleServices::DEBUG_SWITCH));
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('invalidConfigurations')]
    public function testAnInvalidConfigurationFails(array $config): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->load($config);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidConfigurations(): iterable
    {
        yield 'format' => [['timestamps' => ['format' => 'epoch']]];
        yield 'summary' => [['summary' => 'sometimes']];
        yield 'channel' => [['debug' => ['channels' => ['']]]];
    }

    public function testTheCompilerPass(): void
    {
        $container = new ContainerBuilder();
        new PhilConsoleBundle()->build($container);

        $passes = array_filter($container->getCompilerPassConfig()->getBeforeRemovingPasses(), static fn (object $pass): bool => $pass instanceof LoggerChannelsPass);
        self::assertCount(1, $passes);
        self::assertSame([], array_filter($container->getCompilerPassConfig()->getBeforeOptimizationPasses(), static fn (object $pass): bool => $pass instanceof LoggerChannelsPass));
    }

    /**
     * @param array<string, mixed> $config
     */
    private function load(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $this->extension()->load([$config], $container);

        return $container;
    }

    private function extension(): ExtensionInterface
    {
        $extension = new PhilConsoleBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        return $extension;
    }
}

<?php

declare(strict_types=1);

namespace Phil\ConsoleBundle\Infrastructure\DependencyInjection;

use Monolog\Logger;
use Phil\ConsoleBundle\Application\EnvironmentGuard;
use Phil\ConsoleBundle\Domain\SummaryMode;
use Phil\ConsoleBundle\Domain\TimestampFormat;
use Phil\ConsoleBundle\Domain\ValueObject\ConsoleSettings;
use Phil\ConsoleBundle\Infrastructure\Command\LintGuardsCommand;
use Phil\ConsoleBundle\Infrastructure\Console\CommandGuardReader;
use Phil\ConsoleBundle\Infrastructure\Console\ConsoleOptions;
use Phil\ConsoleBundle\Infrastructure\Console\StreamStamps;
use Phil\ConsoleBundle\Infrastructure\EventListener\ConsoleListener;
use Phil\ConsoleBundle\Infrastructure\Monolog\ChannelDebugSwitch;
use Phil\ConsoleBundle\Infrastructure\Runtime\PhpMemoryGauge;
use Phil\ConsoleBundle\Infrastructure\Runtime\SystemClock;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\DependencyInjection\Argument\ServiceLocatorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * The services of the `phil_console` configuration.
 *
 * @internal
 */
final class ConsoleServices
{
    public const string DEBUG_SWITCH = 'phil_console.debug_switch';

    public const string MEMORY_GAUGE = 'phil_console.memory_gauge';

    public const string CLOCK = 'phil_console.clock';

    /**
     * @param array{timestamps: array{enabled: bool, format: string}, summary: string, debug: array{enabled: bool, channels: list<string>}} $config
     */
    public static function register(ContainerBuilder $container, array $config): void
    {
        $container->register(self::CLOCK, SystemClock::class);
        $container->register(self::MEMORY_GAUGE, PhpMemoryGauge::class);
        $container->register(CommandGuardReader::class, CommandGuardReader::class);
        $container->register(ConsoleOptions::class, ConsoleOptions::class);
        $container->register(EnvironmentGuard::class, EnvironmentGuard::class)->setArguments(['%kernel.environment%']);
        $container->register(StreamStamps::class, StreamStamps::class)->setArguments([new Reference(self::CLOCK)]);

        $settings = new Definition(ConsoleSettings::class, [
            $config['timestamps']['enabled'],
            TimestampFormat::from($config['timestamps']['format']),
            SummaryMode::from($config['summary']),
            $config['debug']['channels'],
        ]);

        $container->register(ConsoleListener::class, ConsoleListener::class)
            ->setArguments([
                new Reference(ConsoleOptions::class),
                new Reference(CommandGuardReader::class),
                new Reference(EnvironmentGuard::class),
                new Reference(StreamStamps::class),
                new Reference(self::MEMORY_GAUGE),
                new Reference(self::CLOCK),
                $settings,
                new Reference(self::DEBUG_SWITCH, ContainerInterface::IGNORE_ON_INVALID_REFERENCE),
            ])
            ->addTag('kernel.event_listener', ['event' => ConsoleEvents::COMMAND, 'method' => 'onCommand'])
            ->addTag('kernel.event_listener', ['event' => ConsoleEvents::TERMINATE, 'method' => 'onTerminate'])
            ->addTag('kernel.reset', ['method' => 'reset']);

        $container->register(LintGuardsCommand::class, LintGuardsCommand::class)
            ->setArguments([new Reference(CommandGuardReader::class)])
            ->addTag('console.command', ['command' => 'lint:console-guards']);

        if ($config['debug']['enabled'] && class_exists(Logger::class)) {
            $container->register(self::DEBUG_SWITCH, ChannelDebugSwitch::class)
                ->setArguments([new ServiceLocatorArgument([]), []])
                ->addTag('kernel.reset', ['method' => 'reset']);
        }
    }
}

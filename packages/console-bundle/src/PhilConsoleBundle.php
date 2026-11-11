<?php

declare(strict_types=1);

namespace Phil\ConsoleBundle;

use Override;
use Phil\ConsoleBundle\Domain\SummaryMode;
use Phil\ConsoleBundle\Domain\TimestampFormat;
use Phil\ConsoleBundle\Infrastructure\DependencyInjection\ConsoleServices;
use Phil\ConsoleBundle\Infrastructure\DependencyInjection\LoggerChannelsPass;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * Safer console commands:
 *
 * ```yaml
 * phil_console:
 *     timestamps: { enabled: false, format: iso }
 *     summary: verbose
 *     debug: { enabled: true, channels: [app, doctrine] }
 * ```
 *
 * `#[ForbidEnvironment]` refuses a command in the listed kernel environments and `#[ConfirmEnvironment]` asks for the
 * environment's name before it runs. Every command gets `--timestamps`, `--force-environment` and, with Monolog,
 * `--debug-phil`; the summary of its duration and memory is written at its end, and `lint:console-guards` lists the
 * guarded commands.
 */
final class PhilConsoleBundle extends AbstractBundle
{
    #[Override]
    protected string $extensionAlias = 'phil_console';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->arrayNode('timestamps')
                    ->info('The time before each output line.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->info('Whether every command writes the time without --timestamps.')->defaultFalse()->end()
                        ->enumNode('format')
                            ->info('"iso", the moment to the millisecond, or "relative", the seconds since the command started.')
                            ->values(array_map(static fn (TimestampFormat $format): string => $format->value, TimestampFormat::cases()))
                            ->defaultValue(TimestampFormat::Iso->value)
                        ->end()
                    ->end()
                ->end()
                ->enumNode('summary')
                    ->info('When the duration and memory summary is written at the end of a command: "always", "verbose" (with -v) or "never".')
                    ->values(array_map(static fn (SummaryMode $mode): string => $mode->value, SummaryMode::cases()))
                    ->defaultValue(SummaryMode::Verbose->value)
                ->end()
                ->arrayNode('debug')
                    ->info('The --debug-phil option, which prints the debug logs of Monolog channels.')
                    ->canBeDisabled()
                    ->children()
                        ->arrayNode('channels')
                            ->info('The channels --debug-phil prints when it names none; empty for every channel.')
                            ->scalarPrototype()->cannotBeEmpty()->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @phpstan-var array{timestamps: array{enabled: bool, format: string}, summary: string, debug: array{enabled: bool, channels: list<string>}} $config */
        ConsoleServices::register($builder, $config);
    }

    #[Override]
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new LoggerChannelsPass(), PassConfig::TYPE_BEFORE_REMOVING);
    }
}

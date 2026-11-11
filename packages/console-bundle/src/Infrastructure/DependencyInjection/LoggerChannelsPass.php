<?php

declare(strict_types=1);

namespace Phil\ConsoleBundle\Infrastructure\DependencyInjection;

use Override;
use Phil\ConsoleBundle\Infrastructure\Monolog\ChannelDebugSwitch;
use Symfony\Component\DependencyInjection\Argument\ServiceLocatorArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Gives the Monolog debug switch the channels of the container: `monolog.logger` as `app`, and each
 * `monolog.logger.<channel>`. Without a channel, the switch is removed, and `--debug-phil` with it.
 *
 * It runs before the removal of unused services, once every pass of the optimization has declared its channels.
 */
final class LoggerChannelsPass implements CompilerPassInterface
{
    #[Override]
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(ConsoleServices::DEBUG_SWITCH) || ChannelDebugSwitch::class !== $container->getDefinition(ConsoleServices::DEBUG_SWITCH)->getClass()) {
            return;
        }

        $loggers = [];

        foreach (array_keys($container->getDefinitions()) as $id) {
            if ('monolog.logger' === $id) {
                $loggers['app'] = new Reference($id);
            } elseif (str_starts_with($id, 'monolog.logger.')) {
                $loggers[substr($id, \strlen('monolog.logger.'))] = new Reference($id);
            }
        }

        if ([] === $loggers) {
            $container->removeDefinition(ConsoleServices::DEBUG_SWITCH);

            return;
        }

        ksort($loggers);
        $container->getDefinition(ConsoleServices::DEBUG_SWITCH)
            ->setArguments([new ServiceLocatorArgument($loggers), array_map(strval(...), array_keys($loggers))]);
    }
}

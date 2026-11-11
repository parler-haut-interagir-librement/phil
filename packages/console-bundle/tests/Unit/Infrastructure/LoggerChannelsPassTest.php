<?php

declare(strict_types=1);

namespace Phil\ConsoleBundle\Tests\Unit\Infrastructure;

use Monolog\Logger;
use Phil\ConsoleBundle\Application\Contract\DebugSwitch;
use Phil\ConsoleBundle\Infrastructure\DependencyInjection\ConsoleServices;
use Phil\ConsoleBundle\Infrastructure\DependencyInjection\LoggerChannelsPass;
use Phil\ConsoleBundle\Infrastructure\Monolog\ChannelDebugSwitch;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Argument\ServiceLocatorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(LoggerChannelsPass::class)]
final class LoggerChannelsPassTest extends TestCase
{
    public function testTheChannelsOfMonolog(): void
    {
        $container = $this->container(ChannelDebugSwitch::class);
        $container->register('monolog.logger', Logger::class);
        $container->register('monolog.logger.security', Logger::class);
        $container->register('monolog.logger.doctrine', Logger::class);
        $container->register('monolog.logger_prototype', Logger::class);
        $container->register('logger.other', Logger::class);
        $container->register('monolog.logger.404', Logger::class);

        new LoggerChannelsPass()->process($container);

        $definition = $container->getDefinition(ConsoleServices::DEBUG_SWITCH);
        self::assertSame(['404', 'app', 'doctrine', 'security'], $definition->getArgument(1));
        self::assertEquals(new ServiceLocatorArgument([
            '404' => new Reference('monolog.logger.404'),
            'app' => new Reference('monolog.logger'),
            'doctrine' => new Reference('monolog.logger.doctrine'),
            'security' => new Reference('monolog.logger.security'),
        ]), $definition->getArgument(0));
    }

    public function testWithoutChannelTheSwitchIsRemoved(): void
    {
        $container = $this->container(ChannelDebugSwitch::class);

        new LoggerChannelsPass()->process($container);

        self::assertFalse($container->hasDefinition(ConsoleServices::DEBUG_SWITCH));
    }

    public function testAnotherSwitchIsLeftAlone(): void
    {
        $container = $this->container(DebugSwitch::class);

        new LoggerChannelsPass()->process($container);

        self::assertSame([], $container->getDefinition(ConsoleServices::DEBUG_SWITCH)->getArguments());
        new LoggerChannelsPass()->process(new ContainerBuilder());
    }

    private function container(string $class): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->register(ConsoleServices::DEBUG_SWITCH, $class);

        return $container;
    }
}

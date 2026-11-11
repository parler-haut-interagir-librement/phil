<?php

declare(strict_types=1);

namespace Phil\RouteAccessCheckBundle\Tests\Unit;

use Phil\RouteAccessCheck\ControllerLocator;
use Phil\RouteAccessCheck\Detector\PublicRouteDetector;
use Phil\RouteAccessCheck\Detector\SecurityAttributeDetector;
use Phil\RouteAccessCheck\Detector\SecurityConfigDetector;
use Phil\RouteAccessCheck\Format\GitHubFormatter;
use Phil\RouteAccessCheck\Format\JsonFormatter;
use Phil\RouteAccessCheck\Format\JunitFormatter;
use Phil\RouteAccessCheck\Format\TextFormatter;
use Phil\RouteAccessCheck\IgnoreList;
use Phil\RouteAccessCheck\RouteAccessChecker;
use Phil\RouteAccessCheck\RouteReader;
use Phil\RouteAccessCheck\Security\SecurityMap;
use Phil\RouteAccessCheckBundle\Infrastructure\Console\CheckRouteAccessCommand;
use Phil\RouteAccessCheckBundle\Infrastructure\DependencyInjection\SecurityMapPass;
use Phil\RouteAccessCheckBundle\PhilRouteAccessCheckBundle;
use Phil\RouteAccessCheckBundle\Tests\Unit\Fixture\CsvFormatter;
use Phil\RouteAccessCheckBundle\Tests\Unit\Fixture\HeaderDetector;
use Phil\RouteAccessCheckBundle\Tests\Unit\Fixture\StaticRouter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[CoversClass(PhilRouteAccessCheckBundle::class)]
final class PhilRouteAccessCheckBundleTest extends TestCase
{
    public function testTheDefaults(): void
    {
        $container = $this->load([]);

        self::assertSame([], $container->getParameter(PhilRouteAccessCheckBundle::SECURITY_MAP));
        self::assertSame([], $container->getParameter(PhilRouteAccessCheckBundle::CONTROLLER_CLASSES));
        self::assertSame([SecurityMap::class, 'fromArray'], $container->getDefinition(SecurityMap::class)->getFactory());
        self::assertSame(['%phil_route_access_check.security_map%'], $container->getDefinition(SecurityMap::class)->getArguments());
        self::assertSame(['%phil_route_access_check.controller_classes%'], $container->getDefinition(ControllerLocator::class)->getArguments());
        self::assertEquals([new Reference(ControllerLocator::class)], $container->getDefinition(RouteReader::class)->getArguments());
        self::assertSame([['_profiler', '_wdt', '_preview_error'], ['/_profiler', '/_wdt', '/_error']], $container->getDefinition(IgnoreList::class)->getArguments());
        self::assertSame([[IsGranted::class]], $container->getDefinition(SecurityAttributeDetector::class)->getArguments());
        self::assertSame(['public'], $container->getDefinition(PublicRouteDetector::class)->getArguments());
        self::assertEquals([new Reference(SecurityMap::class)], $container->getDefinition(SecurityConfigDetector::class)->getArguments());
        self::assertSame(['%kernel.project_dir%'], $container->getDefinition(GitHubFormatter::class)->getArguments());

        foreach ([SecurityAttributeDetector::class, PublicRouteDetector::class, SecurityConfigDetector::class] as $detector) {
            self::assertTrue($container->getDefinition($detector)->hasTag('phil_route_access_check.detector'), $detector);
        }

        foreach ([TextFormatter::class, JsonFormatter::class, GitHubFormatter::class, JunitFormatter::class] as $formatter) {
            self::assertTrue($container->getDefinition($formatter)->hasTag('phil_route_access_check.formatter'), $formatter);
        }

        $checker = $container->getDefinition(RouteAccessChecker::class)->getArguments();
        self::assertEquals(new TaggedIteratorArgument('phil_route_access_check.detector'), $checker[0]);
        self::assertEquals([new Reference(IgnoreList::class), new Reference(RouteReader::class)], [$checker[1], $checker[2]]);

        $command = $container->getDefinition(CheckRouteAccessCommand::class);
        self::assertEquals([new Reference(RouteAccessChecker::class), new Reference('router'), new TaggedIteratorArgument('phil_route_access_check.formatter'), null], $command->getArguments());
        self::assertTrue($command->hasTag('console.command'));
    }

    public function testTheConfigurationReachesTheServices(): void
    {
        $container = $this->load([
            'baseline' => '/app/route-access-baseline.json',
            'public_option' => 'anonymous',
            'attributes' => ['App\Security\RequiresRole'],
            'ignore' => ['route_names' => ['health_'], 'paths' => ['/.well-known/']],
        ]);

        self::assertSame([['health_'], ['/.well-known/']], $container->getDefinition(IgnoreList::class)->getArguments());
        self::assertSame(['anonymous'], $container->getDefinition(PublicRouteDetector::class)->getArguments());
        self::assertSame([['App\Security\RequiresRole']], $container->getDefinition(SecurityAttributeDetector::class)->getArguments());
        self::assertSame('/app/route-access-baseline.json', $container->getDefinition(CheckRouteAccessCommand::class)->getArgument(3));
    }

    public function testNothingIsRegisteredOutsideItsEnvironments(): void
    {
        self::assertFalse($this->load([], 'prod')->hasDefinition(RouteAccessChecker::class));
        self::assertFalse($this->load([], null)->hasDefinition(RouteAccessChecker::class));
        self::assertFalse($this->load([], 'prod')->hasParameter(PhilRouteAccessCheckBundle::SECURITY_MAP));
        self::assertTrue($this->load(['environments' => ['ci']], 'ci')->hasDefinition(RouteAccessChecker::class));
        self::assertTrue($this->load([], 'dev')->hasDefinition(RouteAccessChecker::class));
        self::assertFalse($this->load(['environments' => ['ci']], 'test')->hasDefinition(CheckRouteAccessCommand::class));
    }

    public function testTheApplicationsDetectorsAndFormatsAreAutoconfigured(): void
    {
        $routes = new RouteCollection();
        $routes->add('signed', new Route('/download', options: ['signed' => true]));

        $container = $this->load([]);
        $container->register(HeaderDetector::class)->setAutoconfigured(true);
        $container->register(CsvFormatter::class)->setAutoconfigured(true);
        $container->register('router', StaticRouter::class)->setArguments([$routes]);
        $container->getDefinition(CheckRouteAccessCommand::class)->setPublic(true);
        $container->compile(true);

        $command = $container->get(CheckRouteAccessCommand::class);
        self::assertInstanceOf(CheckRouteAccessCommand::class, $command);

        $tester = new CommandTester($command);
        self::assertSame(0, $tester->execute(['--format' => 'csv']));
        self::assertSame("signed,protected\n", $tester->getDisplay());
    }

    public function testTheBuildAddsThePass(): void
    {
        $container = new ContainerBuilder();
        new PhilRouteAccessCheckBundle()->build($container);

        $passes = array_filter($container->getCompilerPassConfig()->getBeforeOptimizationPasses(), static fn (object $pass): bool => $pass instanceof SecurityMapPass);
        self::assertCount(1, $passes);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function load(array $config, ?string $environment = 'test'): ContainerBuilder
    {
        $container = new ContainerBuilder();

        if (null !== $environment) {
            $container->setParameter('kernel.environment', $environment);
        }

        $container->setParameter('kernel.project_dir', '/app');
        $extension = new PhilRouteAccessCheckBundle()->getContainerExtension();
        self::assertNotNull($extension);
        $extension->load([$config], $container);

        return $container;
    }
}

<?php

declare(strict_types=1);

namespace Phil\RouteAccessCheckBundle\Tests\Unit\DependencyInjection;

use Phil\RouteAccessCheck\RouteAccessChecker;
use Phil\RouteAccessCheck\Security\SecurityMap;
use Phil\RouteAccessCheckBundle\Infrastructure\DependencyInjection\SecurityMapPass;
use Phil\RouteAccessCheckBundle\Tests\Unit\Fixture\CsvFormatter;
use Phil\RouteAccessCheckBundle\Tests\Unit\Fixture\HeaderDetector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpFoundation\ChainRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcher\AttributesRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcher\HostRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcher\IpsRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcher\IsJsonRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcher\MethodRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcher\PathRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcher\PortRequestMatcher;

/**
 * The services SecurityBundle builds, as its extension leaves them before the optimisation passes.
 */
#[CoversClass(SecurityMapPass::class)]
final class SecurityMapPassTest extends TestCase
{
    public function testNothingIsReadWhenTheCheckIsNotRegistered(): void
    {
        $container = $this->security();
        $container->removeDefinition(RouteAccessChecker::class);
        new SecurityMapPass()->process($container);

        self::assertFalse($container->hasParameter('phil_route_access_check.security_map'));
        self::assertFalse($container->hasParameter('phil_route_access_check.controller_classes'));
    }

    public function testWithoutSecurityTheMapIsEmpty(): void
    {
        $container = new ContainerBuilder();
        $container->register(RouteAccessChecker::class);
        new SecurityMapPass()->process($container);

        self::assertSame(['firewalls' => [], 'access_control' => []], $container->getParameter('phil_route_access_check.security_map'));
        self::assertSame([], $container->getParameter('phil_route_access_check.controller_classes'));
    }

    public function testItReadsTheFirewallsAndTheAccessMap(): void
    {
        $container = $this->security();
        new SecurityMapPass()->process($container);
        $map = $container->getParameter('phil_route_access_check.security_map');

        self::assertSame([
            'firewalls' => [
                ['name' => 'dev', 'security' => false, 'request' => ['path' => '^/(_profiler|_wdt)']],
                ['name' => 'api', 'security' => true, 'request' => ['host' => '^api\.', 'methods' => ['GET', 'POST']]],
                ['name' => 'custom', 'security' => true, 'request' => ['matcher' => 'app.firewall_matcher']],
                ['name' => 'json', 'security' => true, 'request' => ['matcher' => '.security.request_matcher.json']],
                ['name' => 'dangling', 'security' => true, 'request' => ['matcher' => 'm.dangling']],
                ['name' => 'unconfigured', 'security' => true],
                ['name' => 'unset', 'security' => true],
                ['name' => 'main', 'security' => true],
            ],
            'access_control' => [
                ['request' => ['path' => '^/login'], 'roles' => ['PUBLIC_ACCESS'], 'requires_channel' => null],
                ['request' => ['path' => '^/admin', 'ips' => ['10.0.0.1', '10.0.0.2'], 'port' => 8443], 'roles' => [], 'allow_if' => 'is_granted("ROLE_ADMIN")', 'requires_channel' => 'https'],
                ['request' => ['attributes' => ['_route' => 'export']], 'roles' => ['ROLE_EXPORT'], 'requires_channel' => null],
                ['request' => ['methods' => ['DELETE']], 'roles' => ['ROLE_ADMIN'], 'allow_if' => 'an expression', 'requires_channel' => null],
                ['request' => ['matcher' => 'app.missing'], 'roles' => [], 'requires_channel' => null],
                ['request' => [], 'roles' => ['ROLE_USER'], 'requires_channel' => null],
                ['request' => ['host' => '%env(ADMIN_HOST)%'], 'roles' => ['ROLE_STAFF'], 'requires_channel' => null],
            ],
        ], $map);

        self::assertIsArray($map);
        self::assertCount(7, SecurityMap::fromArray($map)->accessControl);
        self::assertSame(['app.admin_controller' => HeaderDetector::class, 'app.csv_controller' => CsvFormatter::class], $container->getParameter('phil_route_access_check.controller_classes'));
    }

    public function testAFirewallMapKeyedByPositionNamesItsFirewallsSo(): void
    {
        $container = new ContainerBuilder();
        $container->register(RouteAccessChecker::class);
        $container->register('security.firewall.map')->setArguments([null, new IteratorArgument([null])]);
        new SecurityMapPass()->process($container);

        self::assertSame(['firewalls' => [['name' => '0', 'security' => true]], 'access_control' => []], $container->getParameter('phil_route_access_check.security_map'));
    }

    public function testAnUnreadableFirewallMapIsEmpty(): void
    {
        $container = new ContainerBuilder();
        $container->register(RouteAccessChecker::class);
        $container->register('security.firewall.map')->setArguments([null, []]);
        new SecurityMapPass()->process($container);

        self::assertSame(['firewalls' => [], 'access_control' => []], $container->getParameter('phil_route_access_check.security_map'));
    }

    private function security(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('admin_path', '^/admin');
        $container->setParameter('env(ADMIN_HOST)', 'admin.example.com');
        $container->setParameter('admin_host', '%env(ADMIN_HOST)%');
        $container->setParameter('role_export', 'ROLE_EXPORT');
        $container->register(RouteAccessChecker::class);

        $matcher = static function (string $id, array $parts) use ($container): Reference {
            $references = [];

            foreach ($parts as $index => [$class, $argument]) {
                $container->register($id.'.'.$index, $class)->setArguments([$argument]);
                $references[] = new Reference($id.'.'.$index);
            }

            $container->register($id, ChainRequestMatcher::class)->setArguments([$references]);

            return new Reference($id);
        };

        $dev = $matcher('m.dev', [[PathRequestMatcher::class, '^/(_profiler|_wdt)']]);
        $api = $matcher('m.api', [[HostRequestMatcher::class, '^api\.'], [MethodRequestMatcher::class, 'GET, POST']]);
        $json = $matcher('.security.request_matcher.json', [[PathRequestMatcher::class, '^/json'], [IsJsonRequestMatcher::class, null]]);
        $container->register('m.dangling', ChainRequestMatcher::class)->setArguments([[new Reference('m.nowhere'), 'm.dev.0']]);
        $container->register('app.firewall_matcher', stdClass::class);

        $firewalls = ['dev' => $dev, 'api' => $api, 'custom' => new Reference('app.firewall_matcher'), 'json' => $json, 'dangling' => new Reference('m.dangling'), 'unconfigured' => null, 'unset' => null, 'main' => null];
        $contexts = [];

        foreach ($firewalls as $name => $reference) {
            $contexts['security.firewall.map.context.'.$name] = $reference;

            if ('unconfigured' === $name) {
                continue;
            }

            $config = new ChildDefinition('security.firewall.config');

            if ('unset' !== $name) {
                $config->replaceArgument(3, 'dev' !== $name);
            }

            $container->setDefinition('security.firewall.map.config.'.$name, $config);
        }

        $container->register('security.firewall.map')->setArguments([new Reference('locator'), new IteratorArgument($contexts)]);

        $container->register('.security.expression.admin', Definition::class)->setArguments(['is_granted("ROLE_ADMIN")']);
        $container->register('.security.expression.dynamic', Definition::class)->setArguments([new Reference('x')]);
        $container->setAlias('m.alias', 'm.login');

        $accessMap = $container->register('security.access_map');
        $matcher('m.login', [[PathRequestMatcher::class, '^/login']]);
        $accessMap->addMethodCall('add', ['not-a-reference', ['ROLE_Y']]);
        $accessMap->addMethodCall('setSomething', [new Reference('m.all')]);
        $accessMap->addMethodCall('add', [new Reference('m.alias'), ['PUBLIC_ACCESS'], null]);
        $accessMap->addMethodCall('add', [$matcher('m.admin', [[PathRequestMatcher::class, '%admin_path%'], [IpsRequestMatcher::class, ['10.0.0.1', '10.0.0.2']], [PortRequestMatcher::class, 8443]]), [new Reference('.security.expression.admin')], 'https']);
        $accessMap->addMethodCall('add', [$matcher('m.export', [[AttributesRequestMatcher::class, ['_route' => 'export']]]), ['%role_export%'], null]);
        $accessMap->addMethodCall('add', [$matcher('m.delete', [[MethodRequestMatcher::class, ['DELETE']]]), ['ROLE_ADMIN', new Reference('.security.expression.dynamic'), 3], 7]);
        $accessMap->addMethodCall('add', [new Reference('app.missing'), 'ROLE_X']);
        $accessMap->addMethodCall('add', [$matcher('m.all', []), ['ROLE_USER']]);
        $accessMap->addMethodCall('add', [$matcher('m.staff', [[HostRequestMatcher::class, '%admin_host%']]), ['ROLE_STAFF']]);

        $container->register('app.admin_controller', HeaderDetector::class)->addTag('controller.service_arguments');
        $container->register('app.csv_controller', CsvFormatter::class)->addTag('controller.service_arguments');
        $container->register(HeaderDetector::class, HeaderDetector::class)->addTag('controller.service_arguments');
        $container->register('app.classless')->addTag('controller.service_arguments');

        return $container;
    }
}

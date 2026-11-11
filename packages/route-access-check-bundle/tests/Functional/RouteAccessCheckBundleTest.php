<?php

declare(strict_types=1);

namespace Phil\RouteAccessCheckBundle\Tests\Functional;

use Override;
use Phil\RouteAccessCheckBundle\Infrastructure\DependencyInjection\SecurityMapPass;
use Phil\RouteAccessCheckBundle\PhilRouteAccessCheckBundle;
use Phil\RouteAccessCheckBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * The command in an application whose security configuration SecurityBundle reads: the firewalls and access_control
 * entries reach the check as the application runs them.
 */
#[CoversClass(PhilRouteAccessCheckBundle::class)]
#[CoversClass(SecurityMapPass::class)]
final class RouteAccessCheckBundleTest extends KernelTestCase
{
    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    #[Override]
    protected function setUp(): void
    {
        if (!ContainerBuilder::willBeAvailable('symfony/event-dispatcher', EventDispatcher::class, ['symfony/dependency-injection'])) {
            self::markTestSkipped('The event dispatcher is installed for development only: no kernel can boot.');
        }
    }

    public function testEveryRouteIsJudgedFromTheApplicationsConfiguration(): void
    {
        $kernel = self::bootKernel(['environment' => 'test', 'debug' => false]);
        $tester = new CommandTester(new Application($kernel)->find('phil:route-access:check'));

        self::assertSame(1, $tester->execute(['--format' => 'json']));

        /** @var array{routes: list<array{name: string, status: string, uncoveredMethods: list<string>, rules: list<array{description: string}>}>, unusedRules: list<array{id: string, description: string}>} $report */
        $report = json_decode($tester->getDisplay(), true, 32, \JSON_THROW_ON_ERROR);

        $statuses = array_column($report['routes'], 'status', 'name');
        ksort($statuses);
        self::assertSame([
            '_wdt' => 'ignored',
            'admin_dashboard' => 'protected',
            'admin_help' => 'conflict',
            'api_items' => 'uncovered',
            'health' => 'public',
            'login' => 'public',
            'reports' => 'uncovered',
            'reports_by_service' => 'protected',
            'status' => 'conflict',
        ], $statuses);

        $byName = array_column($report['routes'], null, 'name');
        self::assertSame(['GET', 'HEAD', 'PATCH', 'DELETE', 'OPTIONS', '*'], $byName['api_items']['uncoveredMethods']);
        self::assertSame("access_control #3 (path ^/api, methods POST|PUT): allow_if \"is_granted('ROLE_API')\"", $byName['api_items']['rules'][1]['description']);
        self::assertSame('access_control #2 (path ^/admin): ROLE_ADMIN', $byName['admin_dashboard']['rules'][1]['description']);
        self::assertSame(['id' => 'access_control#4', 'description' => 'access_control #4 (path ^/never): ROLE_NOBODY'], $report['unusedRules'][0]);
        self::assertCount(1, $report['unusedRules']);
    }

    public function testNothingIsRegisteredInProduction(): void
    {
        $kernel = self::bootKernel(['environment' => 'prod', 'debug' => false]);

        self::assertFalse(new Application($kernel)->has('phil:route-access:check'));
        self::assertFalse($kernel->getContainer()->hasParameter(PhilRouteAccessCheckBundle::SECURITY_MAP));
    }
}

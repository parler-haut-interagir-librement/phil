<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Tests\Unit\Infrastructure;

use Phil\AuditBundle\Application\AuditTrail;
use Phil\AuditBundle\Domain\Chain\AuditRow;
use Phil\CoursesBundle\Domain\Event\RetryGranted;
use Phil\CoursesBundle\Domain\Event\RetryRefused;
use Phil\CoursesBundle\Domain\Event\RetryRequested;
use Phil\CoursesBundle\Domain\Exception\InvalidCourse;
use Phil\CoursesBundle\Domain\Exception\PackageRefused;
use Phil\CoursesBundle\Domain\ValueObject\LaunchSessionId;
use Phil\CoursesBundle\Infrastructure\Audit\AuditSecondChances;
use Phil\CoursesBundle\Infrastructure\DependencyInjection\KitSubdirectoryPass;
use Phil\CoursesBundle\Infrastructure\Routing\RoutedCmi5Endpoints;
use Phil\CoursesBundle\Infrastructure\Security\SecurityCurrentLearner;
use Phil\CoursesBundle\Infrastructure\Storage\FilesystemContentStorage;
use Phil\CoursesBundle\Infrastructure\Twig\CoursesExtension;
use Phil\CoursesBundle\Infrastructure\Twig\KitComponents;
use Phil\CoursesBundle\Tests\Unit\Fixture\AuditRows;
use Phil\CoursesBundle\Tests\Unit\Fixture\JsonFields;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;

#[CoversClass(AuditSecondChances::class)]
#[CoversClass(SecurityCurrentLearner::class)]
#[CoversClass(FilesystemContentStorage::class)]
#[CoversClass(KitComponents::class)]
#[CoversClass(CoursesExtension::class)]
#[CoversClass(KitSubdirectoryPass::class)]
#[CoversClass(RoutedCmi5Endpoints::class)]
final class IntegrationsTest extends TestCase
{
    public function testTheSecondChancesAreWrittenToTheTrail(): void
    {
        $rows = new AuditRows();
        $trail = new AuditTrail($rows, new JsonFields(), new MockClock('2026-11-13 10:00 UTC'));
        $audit = new AuditSecondChances($trail, 'learning');

        $audit->requested(new RetryRequested('r1', 'e1', 'a1'));
        $audit->granted(new RetryGranted('r1', 'e1', 'a1', 'teacher-1'));
        $audit->refused(new RetryRefused('r2', 'e1', 'a1', 'teacher-2'));

        $written = $rows->rows;
        self::assertSame(['courses.retry.requested', 'courses.retry.granted', 'courses.retry.refused'], array_map(static fn (AuditRow $row): ?string => $row->facts->action, $written));
        self::assertSame([null, 'teacher-1', 'teacher-2'], array_map(static fn (AuditRow $row): ?string => $row->facts->actor, $written));
        self::assertSame(['r1', 'r1', 'r2'], array_map(static fn (AuditRow $row): ?string => $row->facts->subject?->id, $written));
        self::assertSame('course_retry', $written[0]->facts->subject?->type);
        self::assertSame(['learning'], array_values(array_unique(array_map(static fn (AuditRow $row): string => $row->segment, $written))));
        foreach ($written as $row) {
            self::assertStringContainsString('"enrollment":"e1"', $row->payload);
            self::assertStringContainsString('"activity":"a1"', $row->payload);
        }
    }

    public function testTheLearnerIsTheUserSignedIn(): void
    {
        $tokens = new TokenStorage();
        self::assertNull(new SecurityCurrentLearner($tokens)->current());
        $learner = new SecurityCurrentLearner($tokens);
        self::assertNull(new SecurityCurrentLearner(null)->current());

        $tokens->setToken(new UsernamePasswordToken(new InMemoryUser('ada', null), 'main'));
        self::assertSame('ada', $learner->current()?->value);

        $tokens->setToken(new UsernamePasswordToken(new InMemoryUser('ada@example.org', null), 'main'));
        $this->expectException(InvalidCourse::class);
        $learner->current();
    }

    public function testTheFilesAreKeptInTheDirectory(): void
    {
        $directory = sys_get_temp_dir().'/phil-courses-storage-'.bin2hex(random_bytes(4));
        $storage = new FilesystemContentStorage($directory.'/');

        $storage->write('c1/a/b.html', 'Hello');
        self::assertTrue($storage->exists('c1/a/b.html'));
        self::assertFalse($storage->exists('c1/a/missing.html'));
        self::assertSame('Hello', $storage->read('c1/a/b.html'));
        self::assertSame('Hello', file_get_contents($directory.'/c1/a/b.html'));
        self::assertNull($storage->read('c1/missing.html'));
        self::assertNull($storage->read('c1/a'));

        $storage->removeDirectory('c1');
        self::assertDirectoryDoesNotExist($directory.'/c1');

        $this->expectException(PackageRefused::class);
        $storage->read('../outside');
    }

    public function testTheRecipesOfTheKitAreNamedAfterTheToolkitSubdirectory(): void
    {
        self::assertSame('Button', new CoursesExtension(new KitComponents())->kit('Button'));
        self::assertSame('ui:kit:Button', new CoursesExtension(new KitComponents('/ui/kit/'))->kit('Button'));

        $container = new ContainerBuilder();
        new KitSubdirectoryPass()->process($container);
        self::assertFalse($container->hasDefinition(KitComponents::class));

        $container->register(KitComponents::class, KitComponents::class)->setArguments([null]);
        new KitSubdirectoryPass()->process($container);
        self::assertSame('', $container->getDefinition(KitComponents::class)->getArgument(0));

        $container->getDefinition(KitComponents::class)->setArguments([null]);
        $container->setParameter('.ux_toolkit.components.subdirectory', 'ui');
        new KitSubdirectoryPass()->process($container);
        self::assertSame('ui', $container->getDefinition(KitComponents::class)->getArgument(0));

        $container->getDefinition(KitComponents::class)->setArguments(['own']);
        new KitSubdirectoryPass()->process($container);
        self::assertSame('own', $container->getDefinition(KitComponents::class)->getArgument(0));
    }

    public function testTheEndpointsAreAbsoluteUrlsOfTheRoutes(): void
    {
        $routes = new RouteCollection();
        $routes->add('phil_courses_xapi', new Route('/cmi5/xapi/'));
        $routes->add('phil_courses_cmi5_fetch', new Route('/cmi5/fetch/{session}'));

        $endpoints = new RoutedCmi5Endpoints(new UrlGenerator($routes, new RequestContext('', 'GET', 'learn.example.org', 'https')));

        self::assertSame('https://learn.example.org/cmi5/xapi/', $endpoints->endpoint());
        self::assertSame('https://learn.example.org/cmi5/fetch/s-1', $endpoints->fetch(LaunchSessionId::of('s-1')));
    }
}

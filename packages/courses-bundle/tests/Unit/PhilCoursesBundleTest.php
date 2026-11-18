<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Tests\Unit;

use Phil\CertificatesBundle\Domain\Contract\CourseCompletions;
use Phil\CoursesBundle\Application\ActivityType\Cmi5Activity;
use Phil\CoursesBundle\Application\ActivityType\LinkActivity;
use Phil\CoursesBundle\Application\ActivityType\PageActivity;
use Phil\CoursesBundle\Application\ActivityType\ScormActivity;
use Phil\CoursesBundle\Application\ActivityTypes;
use Phil\CoursesBundle\Application\Availabilities;
use Phil\CoursesBundle\Application\Cmi5\Cmi5Launcher;
use Phil\CoursesBundle\Application\Cmi5\Cmi5Settings;
use Phil\CoursesBundle\Application\Cmi5\Cmi5Statements;
use Phil\CoursesBundle\Application\Cmi5\Cmi5Tokens;
use Phil\CoursesBundle\Application\Cmi5\IssueWaivedStatement;
use Phil\CoursesBundle\Application\Completions;
use Phil\CoursesBundle\Application\ContentUrls;
use Phil\CoursesBundle\Application\Contract\ActivityType;
use Phil\CoursesBundle\Application\Contract\Cmi5Endpoints;
use Phil\CoursesBundle\Application\Contract\ContentStorage;
use Phil\CoursesBundle\Application\Contract\CurrentLearner;
use Phil\CoursesBundle\Application\Contract\LearningRecordStore;
use Phil\CoursesBundle\Application\Courses;
use Phil\CoursesBundle\Application\Enrollments;
use Phil\CoursesBundle\Application\Import\CourseImporter;
use Phil\CoursesBundle\Application\Import\ResultImporter;
use Phil\CoursesBundle\Application\Learning;
use Phil\CoursesBundle\Application\Scorm\ScormRuntime;
use Phil\CoursesBundle\Application\Scorm\ScormTokens;
use Phil\CoursesBundle\Application\SecondChances;
use Phil\CoursesBundle\Domain\Contract\CourseRepository;
use Phil\CoursesBundle\Domain\Contract\EnrollmentRepository;
use Phil\CoursesBundle\Domain\Contract\LaunchSessionRepository;
use Phil\CoursesBundle\Domain\Contract\RetryRequestRepository;
use Phil\CoursesBundle\Domain\Event\ActivitySatisfied;
use Phil\CoursesBundle\Domain\Event\RetryGranted;
use Phil\CoursesBundle\Domain\Event\RetryRefused;
use Phil\CoursesBundle\Domain\Event\RetryRequested;
use Phil\CoursesBundle\Domain\Event\RetryWaived;
use Phil\CoursesBundle\Infrastructure\Audit\AuditSecondChances;
use Phil\CoursesBundle\Infrastructure\Certificates\CertificatesCourseCompletions;
use Phil\CoursesBundle\Infrastructure\Console\ImportCommand;
use Phil\CoursesBundle\Infrastructure\Console\ImportResultsCommand;
use Phil\CoursesBundle\Infrastructure\Console\RetriesCommand;
use Phil\CoursesBundle\Infrastructure\DependencyInjection\CoursesServices;
use Phil\CoursesBundle\Infrastructure\DependencyInjection\KitSubdirectoryPass;
use Phil\CoursesBundle\Infrastructure\Doctrine\DbalCourseRepository;
use Phil\CoursesBundle\Infrastructure\Doctrine\DbalEnrollmentRepository;
use Phil\CoursesBundle\Infrastructure\Doctrine\DbalLaunchSessionRepository;
use Phil\CoursesBundle\Infrastructure\Doctrine\DbalLearningRecordStore;
use Phil\CoursesBundle\Infrastructure\Doctrine\DbalRetryRequestRepository;
use Phil\CoursesBundle\Infrastructure\Doctrine\DbalScormAttemptRepository;
use Phil\CoursesBundle\Infrastructure\Doctrine\SchemaListener;
use Phil\CoursesBundle\Infrastructure\Http\Cmi5Controller;
use Phil\CoursesBundle\Infrastructure\Http\ContentController;
use Phil\CoursesBundle\Infrastructure\Http\CourseController;
use Phil\CoursesBundle\Infrastructure\Http\CourseRequest;
use Phil\CoursesBundle\Infrastructure\Http\ScormController;
use Phil\CoursesBundle\Infrastructure\Http\ScormFrameController;
use Phil\CoursesBundle\Infrastructure\Http\SecondChancesController;
use Phil\CoursesBundle\Infrastructure\Privacy\ScormPrivacy;
use Phil\CoursesBundle\Infrastructure\Routing\RoutedCmi5Endpoints;
use Phil\CoursesBundle\Infrastructure\Security\SecurityCurrentLearner;
use Phil\CoursesBundle\Infrastructure\Storage\FilesystemContentStorage;
use Phil\CoursesBundle\Infrastructure\Twig\CoursesExtension;
use Phil\CoursesBundle\Infrastructure\Twig\KitComponents;
use Phil\CoursesBundle\Infrastructure\Xapi\HttpLearningRecordStore;
use Phil\CoursesBundle\PhilCoursesBundle;
use Phil\CoursesBundle\Tests\Unit\Fixture\Store;
use Phil\ElearningPackages\Archive\ZipPackageOpener;
use Phil\ElearningPackages\Contract\PackageOpener;
use Phil\ElearningPackages\PackageLimits;
use Phil\ElearningPackages\PackageReader;
use Phil\SafeHttp\Limits;
use Phil\SafeHttp\SafeHttpClient;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(PhilCoursesBundle::class)]
#[CoversClass(CoursesServices::class)]
final class PhilCoursesBundleTest extends TestCase
{
    /** The port of phil/certificates-bundle, named by text: it may not be installed. */
    public function testTheDefaultsWireTheStoresTheTypesAndThePages(): void
    {
        $container = $this->load([]);

        self::assertSame('phil_courses', $this->extension()->getAlias());
        self::assertSame(DbalCourseRepository::class, (string) $container->getAlias(CourseRepository::class));
        self::assertEquals(new Reference('doctrine.dbal.default_connection'), $container->getDefinition(DbalCourseRepository::class)->getArgument(0));
        self::assertSame(DbalLearningRecordStore::class, (string) $container->getAlias(LearningRecordStore::class));
        self::assertSame(SecurityCurrentLearner::class, (string) $container->getAlias(CurrentLearner::class));
        self::assertSame(FilesystemContentStorage::class, (string) $container->getAlias(ContentStorage::class));
        self::assertSame(['%kernel.project_dir%/var/phil_courses/content'], $container->getDefinition(FilesystemContentStorage::class)->getArguments());
        self::assertSame(ZipPackageOpener::class, (string) $container->getAlias(PackageOpener::class));
        self::assertSame(RoutedCmi5Endpoints::class, (string) $container->getAlias(Cmi5Endpoints::class));
        self::assertSame(['UTC'], $container->getDefinition('phil_courses.site_zone')->getArguments());
        self::assertSame([null, false], $container->getDefinition(ContentUrls::class)->getArguments());
        self::assertSame([null, 14_400, false], $container->getDefinition(Cmi5Settings::class)->getArguments());
        self::assertSame('%kernel.secret%', $container->getDefinition(Cmi5Tokens::class)->getArgument(2));
        self::assertSame([PackageLimits::ARCHIVE_BYTES, PackageLimits::ENTRIES, PackageLimits::ENTRY_BYTES, PackageLimits::TOTAL_BYTES, PackageLimits::RATIO, PackageLimits::MANIFEST_BYTES, PackageLimits::DEPTH, PackageLimits::ITEMS], $container->getDefinition(PackageLimits::class)->getArguments());
        self::assertNull($container->getDefinition(CourseImporter::class)->getArgument(5));
        self::assertTrue($container->getDefinition(PageActivity::class)->hasTag(ActivityType::TAG));
        self::assertTrue($container->getDefinition(Cmi5Activity::class)->hasTag(ActivityType::TAG));
        self::assertArrayHasKey(ActivityType::class, $container->getAutoconfiguredInstanceof());
        self::assertSame([['event' => ActivitySatisfied::class]], $container->getDefinition(IssueWaivedStatement::class)->getTag('kernel.event_listener'));
        self::assertSame([], $container->getDefinition(Cmi5Controller::class)->getArgument(3));
        self::assertSame([null, null], \array_slice($container->getDefinition(ContentController::class)->getArguments(), 2));

        foreach ([ImportCommand::class, ImportResultsCommand::class, RetriesCommand::class] as $command) {
            self::assertTrue($container->getDefinition($command)->hasTag('console.command'));
        }

        self::assertSame([['event' => 'postGenerateSchema', 'connection' => 'default']], $container->getDefinition(SchemaListener::class)->getTag('doctrine.event_listener'));
        self::assertSame(['@PhilCourses/layout.html.twig', false, true], \array_slice($container->getDefinition(CourseController::class)->getArguments(), 11));
        self::assertNull($container->getDefinition(KitComponents::class)->getArgument(0));
        self::assertFalse($container->hasDefinition(AuditSecondChances::class), 'without the audit bundle registered');
    }

    public function testTheApplicationNamesItsPortsAndOptions(): void
    {
        $container = $this->load([[
            'connection' => 'courses',
            'timezone' => 'America/Toronto',
            'current_learner' => 'app.learner',
            'layout' => 'base.html.twig',
            'kit_subdirectory' => 'ui',
            'self_enrollment' => true,
            'locked_activities' => 'hide',
            'storage' => ['service' => 'app.storage'],
            'content' => ['base_url' => 'https://Content.example.org:8443/files', 'allow_localhost' => true],
            'import' => ['language' => 'fr', 'limits' => ['archive_bytes' => 10, 'entries' => 2, 'entry_bytes' => 3, 'total_bytes' => 4, 'ratio' => 5, 'manifest_bytes' => 6, 'depth' => 7, 'items' => 8]],
            'cmi5' => ['actor_home_page' => 'https://learn.example.org/', 'session_ttl' => 600, 'secret' => 'own', 'allowed_origins' => ['https://units.example.net', 'https://content.example.org:8443'], 'lrs' => 'app.lrs'],
            'audit' => ['segment' => 'learning'],
        ]], ['PhilAuditBundle']);

        self::assertEquals(new Reference('doctrine.dbal.courses_connection'), $container->getDefinition(DbalCourseRepository::class)->getArgument(0));
        self::assertSame(['America/Toronto'], $container->getDefinition('phil_courses.site_zone')->getArguments());
        self::assertSame('app.learner', (string) $container->getAlias(CurrentLearner::class));
        self::assertFalse($container->hasDefinition(SecurityCurrentLearner::class));
        self::assertSame('app.storage', (string) $container->getAlias(ContentStorage::class));
        self::assertFalse($container->hasDefinition(FilesystemContentStorage::class));
        self::assertSame('app.lrs', (string) $container->getAlias(LearningRecordStore::class));
        self::assertSame(['https://Content.example.org:8443/files', true], $container->getDefinition(ContentUrls::class)->getArguments());
        self::assertSame(['https://learn.example.org/', 600, true], $container->getDefinition(Cmi5Settings::class)->getArguments());
        self::assertSame('own', $container->getDefinition(Cmi5Tokens::class)->getArgument(2));
        self::assertSame([10, 2, 3, 4, 5, 6, 7, 8], $container->getDefinition(PackageLimits::class)->getArguments());
        self::assertSame('fr', $container->getDefinition(CourseImporter::class)->getArgument(5));
        self::assertSame(['https://content.example.org:8443', 'https://units.example.net'], $container->getDefinition(Cmi5Controller::class)->getArgument(3));
        self::assertSame(['https://learn.example.org', 'https://learn.example.org'], \array_slice($container->getDefinition(ContentController::class)->getArguments(), 2));
        self::assertSame(['base.html.twig', true, false], \array_slice($container->getDefinition(CourseController::class)->getArguments(), 11));
        self::assertSame('ui', $container->getDefinition(KitComponents::class)->getArgument(0));
        self::assertSame([['event' => 'postGenerateSchema', 'connection' => 'courses']], $container->getDefinition(SchemaListener::class)->getTag('doctrine.event_listener'));

        $audit = $container->getDefinition(AuditSecondChances::class);
        self::assertSame('learning', $audit->getArgument(1));
        self::assertContains(['event' => RetryGranted::class, 'method' => 'granted'], $audit->getTag('kernel.event_listener'));
        self::assertContains(['event' => RetryWaived::class, 'method' => 'waived'], $audit->getTag('kernel.event_listener'));
        self::assertCount(4, $audit->getTag('kernel.event_listener'));
        self::assertFalse($this->load([['audit' => false]], ['PhilAuditBundle'])->hasDefinition(AuditSecondChances::class));
    }

    public function testTheBoundsOfTheOptionsAreInclusive(): void
    {
        $limits = ['archive_bytes', 'entries', 'entry_bytes', 'total_bytes', 'ratio', 'manifest_bytes', 'depth', 'items'];
        $ones = $this->load([['import' => ['limits' => array_fill_keys($limits, 1)]]]);
        self::assertSame(array_fill(0, 8, 1), $ones->getDefinition(PackageLimits::class)->getArguments());
        self::assertSame(64, $this->load([['import' => ['limits' => ['depth' => 64]]]])->getDefinition(PackageLimits::class)->getArgument(6));

        foreach ($limits as $limit) {
            try {
                $this->load([['import' => ['limits' => [$limit => 0]]]]);
                self::fail('The limit is accepted: '.$limit);
            } catch (InvalidConfigurationException) {
                self::assertNotSame('', $limit);
            }
        }

        foreach ([60 => true, 86_400 => true, 59 => false, 86_401 => false] as $ttl => $accepted) {
            try {
                self::assertSame($ttl, $this->load([['cmi5' => ['session_ttl' => $ttl]]])->getDefinition(Cmi5Settings::class)->getArgument(1));
                self::assertTrue($accepted, 'The time to live is accepted: '.$ttl);
            } catch (InvalidConfigurationException) {
                self::assertFalse($accepted, 'The time to live is refused: '.$ttl);
            }
        }

        self::assertTrue($this->load([['locked_activities' => 'show']])->getDefinition(CourseController::class)->getArgument(13));
    }

    public function testTheScormRunTimeIsWiredWhenEnabledAndItsTokensHoldWithinBounds(): void
    {
        self::assertEquals([new Reference(ContentUrls::class), new Reference(ScormRuntime::class)], $this->load([])->getDefinition(ScormActivity::class)->getArguments());
        self::assertEquals([new Reference(ContentUrls::class), null], $this->load([['scorm' => ['enabled' => false]]])->getDefinition(ScormActivity::class)->getArguments());

        foreach ([300 => true, 86_400 => true, 299 => false, 86_401 => false] as $ttl => $accepted) {
            try {
                self::assertSame($ttl, $this->load([['scorm' => ['token_ttl' => $ttl]]])->getDefinition(ScormTokens::class)->getArgument(2));
                self::assertTrue($accepted, 'The time to live is accepted: '.$ttl);
            } catch (InvalidConfigurationException) {
                self::assertFalse($accepted, 'The time to live is refused: '.$ttl);
            }
        }
    }

    public function testTheOriginOfTheApplicationIsPreferredToTheHomePageOfTheActors(): void
    {
        $container = $this->load([['content' => ['application_origin' => 'https://app.example.net/start'], 'cmi5' => ['actor_home_page' => 'https://learn.example.org/']]]);

        self::assertSame(['https://app.example.net', 'https://learn.example.org'], \array_slice($container->getDefinition(ContentController::class)->getArguments(), 2));
    }

    public function testAWrongOptionIsRefused(): void
    {
        foreach ([['timezone' => 'Mars/Olympus'], ['locked_activities' => 'blur'], ['cmi5' => ['session_ttl' => 30]], ['import' => ['limits' => ['depth' => 65]]], ['import' => ['limits' => ['items' => 0]]]] as $config) {
            try {
                $this->load([$config]);
                self::fail('The option is accepted: '.json_encode($config));
            } catch (InvalidConfigurationException $invalidConfigurationException) {
                self::assertNotSame('', $invalidConfigurationException->getMessage());
            }
        }
    }

    public function testTheFormsAreStatelessAndTheKitPassIsAdded(): void
    {
        $bundle = new PhilCoursesBundle();
        $container = new ContainerBuilder();
        $container->registerExtension(new class extends Extension {
            public function load(array $configs, ContainerBuilder $container): void
            {
            }

            public function getAlias(): string
            {
                return 'framework';
            }
        });
        $extension = $this->extension();
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $extension->prepend($container);
        self::assertSame([['csrf_protection' => ['stateless_token_ids' => ['phil_courses']]]], $container->getExtensionConfig('framework'));

        $bare = new ContainerBuilder();
        $extension->prepend($bare);
        self::assertSame([], $bare->getExtensionConfig('framework'));

        $bundle->build($container);
        self::assertCount(1, array_filter($container->getCompilerPassConfig()->getPasses(), static fn (object $pass): bool => $pass instanceof KitSubdirectoryPass));
    }

    public function testEveryServiceIsGivenEveryArgumentAndTheEntryPointsArePublic(): void
    {
        $container = $this->load([['cmi5' => ['actor_home_page' => 'https://learn.example.org/']]], ['PhilAuditBundle']);
        $definitions = [];

        foreach ($container->getDefinitions() as $id => $definition) {
            $class = $definition->getClass();

            if (null === $class || 'service_container' === $id) {
                continue;
            }

            self::assertTrue(class_exists($class), $class);
            $constructor = new ReflectionClass($class)->getConstructor();
            // The filesystem of FilesystemContentStorage is the one of symfony/filesystem by default.
            $expected = FilesystemContentStorage::class === $id ? 1 : ($constructor?->getNumberOfParameters() ?? 0);
            self::assertCount($expected, $definition->getArguments(), $id);
            $definitions[$id] = $definition->isPublic();
        }

        ksort($definitions);
        $public = [
            ActivityTypes::class, Availabilities::class, Cmi5Controller::class, Cmi5Launcher::class, Cmi5Statements::class, Cmi5Tokens::class,
            Completions::class, ContentController::class, CourseController::class, CourseImporter::class, Courses::class, Enrollments::class,
            Learning::class, ResultImporter::class, SecondChances::class, SecondChancesController::class,
            ScormRuntime::class, ScormTokens::class, ScormController::class, ScormFrameController::class,
        ];
        $private = [
            AuditSecondChances::class, CertificatesCourseCompletions::class, Cmi5Activity::class, Cmi5Settings::class, ContentUrls::class, CourseRequest::class, CoursesExtension::class,
            DbalCourseRepository::class, DbalEnrollmentRepository::class, DbalLaunchSessionRepository::class, DbalLearningRecordStore::class,
            DbalRetryRequestRepository::class, FilesystemContentStorage::class, ImportCommand::class, ImportResultsCommand::class,
            IssueWaivedStatement::class, KitComponents::class, LinkActivity::class, PackageLimits::class, PackageReader::class, PageActivity::class,
            RetriesCommand::class, RoutedCmi5Endpoints::class, SchemaListener::class, ScormActivity::class, SecurityCurrentLearner::class,
            ZipPackageOpener::class, 'phil_courses.site_zone', DbalScormAttemptRepository::class, ScormPrivacy::class,
        ];
        $expected = [...array_fill_keys($public, true), ...array_fill_keys($private, false)];

        ksort($expected);
        self::assertSame($expected, $definitions);

        foreach ([CourseRepository::class, EnrollmentRepository::class, RetryRequestRepository::class, LaunchSessionRepository::class, LearningRecordStore::class, CurrentLearner::class, ContentStorage::class] as $port) {
            self::assertTrue($container->getAlias($port)->isPublic(), $port);
        }

        self::assertEquals([new Reference(PackageLimits::class), null, new Reference(PackageOpener::class)], $container->getDefinition(PackageReader::class)->getArguments());
        self::assertEquals([new Reference(EnrollmentRepository::class)], $container->getDefinition(Completions::class)->getArguments());
        self::assertSame([
            ['event' => RetryRequested::class, 'method' => 'requested'],
            ['event' => RetryGranted::class, 'method' => 'granted'],
            ['event' => RetryRefused::class, 'method' => 'refused'],
            ['event' => RetryWaived::class, 'method' => 'waived'],
        ], $container->getDefinition(AuditSecondChances::class)->getTag('kernel.event_listener'));
    }

    public function testTheCourseCompletionsOfCertificatesAreAnsweredWhenThatPortIsInstalled(): void
    {
        $container = $this->load([]);

        self::assertSame(CertificatesCourseCompletions::class, (string) $container->getAlias(CourseCompletions::class));
        self::assertFalse($container->getAlias(CourseCompletions::class)->isPublic());
        self::assertEquals([new Reference(Completions::class)], $container->getDefinition(CertificatesCourseCompletions::class)->getArguments());
    }

    public function testTheRemoteStoreIsSentToThroughItsOwnGuardedClient(): void
    {
        $container = $this->load([['cmi5' => ['remote_lrs' => ['enabled' => true, 'endpoint' => 'https://lrs.example.org/xapi/', 'username' => 'key', 'password' => 'secret', 'version' => '2.0.0', 'timeout' => 2.5, 'max_duration' => 7.0]]]]);

        self::assertSame(HttpLearningRecordStore::class, (string) $container->getAlias(LearningRecordStore::class));
        self::assertEquals([
            new Reference('phil_courses.lrs_client'),
            'https://lrs.example.org/xapi/',
            new Reference(DbalLearningRecordStore::class),
            'key',
            'secret',
            '2.0.0',
            new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE),
        ], $container->getDefinition(HttpLearningRecordStore::class)->getArguments());
        $client = $container->getDefinition('phil_courses.lrs_client');
        self::assertSame(SafeHttpClient::class, $client->getClass());
        self::assertNull($client->getArgument(0));
        $limits = $client->getArgument(1);
        self::assertInstanceOf(Definition::class, $limits);
        self::assertSame([Limits::class, [2.5, 7.0, 65_536, 0]], [$limits->getClass(), $limits->getArguments()]);
        self::assertSame([['method' => 'reset']], $client->getTag('kernel.reset'));
        $container->set('doctrine.dbal.default_connection', Store::sqlite());
        $container->set('clock', new MockClock());
        self::assertInstanceOf(HttpLearningRecordStore::class, $container->get(LearningRecordStore::class));

        $defaults = $this->load([['cmi5' => ['remote_lrs' => ['enabled' => false, 'endpoint' => 'https://lrs.example.org/']]]]);
        self::assertSame(DbalLearningRecordStore::class, (string) $defaults->getAlias(LearningRecordStore::class));
        self::assertFalse($defaults->hasDefinition(HttpLearningRecordStore::class));
        self::assertFalse($defaults->hasDefinition('phil_courses.lrs_client'));
        self::assertEquals([null, null, '1.0.3', new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE)], \array_slice($this->load([['cmi5' => ['remote_lrs' => ['enabled' => true, 'endpoint' => 'https://lrs.example.org/']]]])->getDefinition(HttpLearningRecordStore::class)->getArguments(), 3));
        $defaultLimits = $this->load([['cmi5' => ['remote_lrs' => ['enabled' => true, 'endpoint' => 'https://lrs.example.org/']]]])->getDefinition('phil_courses.lrs_client')->getArgument(1);
        self::assertInstanceOf(Definition::class, $defaultLimits);
        self::assertSame([5.0, 10.0], \array_slice($defaultLimits->getArguments(), 0, 2));

        foreach ([
            ['lrs' => 'app.lrs', 'remote_lrs' => ['enabled' => true, 'endpoint' => 'https://lrs.example.org/']],
            ['remote_lrs' => ['enabled' => true]],
            ['remote_lrs' => ['enabled' => true, 'endpoint' => 'https://lrs.example.org/', 'version' => '1.0.0']],
            ['remote_lrs' => ['enabled' => true, 'endpoint' => 'https://lrs.example.org/', 'timeout' => 0.05]],
            ['remote_lrs' => ['enabled' => true, 'endpoint' => 'https://lrs.example.org/', 'max_duration' => 121.0]],
        ] as $cmi5) {
            try {
                $this->load([['cmi5' => $cmi5]]);
                self::fail('The store is accepted: '.json_encode($cmi5));
            } catch (InvalidConfigurationException $invalidConfigurationException) {
                self::assertNotSame('', $invalidConfigurationException->getMessage());
            }
        }
    }

    public function testTheAdministrationPageAsksForTheRoleOfTheConfiguration(): void
    {
        $definition = $this->load([['admin' => ['role' => 'ROLE_TEACHER'], 'layout' => 'base.html.twig']])->getDefinition(SecondChancesController::class);

        self::assertEquals(new Reference('security.authorization_checker', ContainerInterface::NULL_ON_INVALID_REFERENCE), $definition->getArgument(5));
        self::assertSame(['ROLE_TEACHER', 'base.html.twig'], \array_slice($definition->getArguments(), 6));
        self::assertSame('ROLE_COURSES_TEACHER', $this->load([])->getDefinition(SecondChancesController::class)->getArgument(6));
        self::assertTrue($definition->hasTag('controller.service_arguments'));
    }

    public function testTheFrenchMessagesAreTheEnglishOnes(): void
    {
        $english = require \dirname(__DIR__, 2).'/translations/phil_courses.en.php';
        $french = require \dirname(__DIR__, 2).'/translations/phil_courses.fr.php';

        self::assertIsArray($english);
        self::assertIsArray($french);
        self::assertSame(array_keys($english), array_keys($french));
    }

    public function testTheOriginsAreListsAndTheirSchemeIsReadWithoutCase(): void
    {
        $container = $this->load([[
            'content' => ['base_url' => 'HTTPS://Content.example.org/files'],
            'cmi5' => ['actor_home_page' => 'HTTPS://Learn.example.org/home', 'allowed_origins' => ['https://content.example.org', 'https://units.example.net']],
        ]]);

        self::assertSame(['https://content.example.org', 'https://units.example.net'], $container->getDefinition(Cmi5Controller::class)->getArgument(3));
        self::assertSame(['https://learn.example.org', 'https://learn.example.org'], \array_slice($container->getDefinition(ContentController::class)->getArguments(), 2));
    }

    /**
     * @param list<array<string, mixed>> $configs
     * @param list<string>               $bundles
     */
    private function load(array $configs, array $bundles = []): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', array_fill_keys($bundles, 'Bundle'));
        $this->extension()->load($configs, $container);

        return $container;
    }

    private function extension(): ExtensionInterface
    {
        $extension = new PhilCoursesBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        return $extension;
    }
}

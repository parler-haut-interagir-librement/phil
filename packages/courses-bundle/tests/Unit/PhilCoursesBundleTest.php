<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Tests\Unit;

use Override;
use Phil\CertificatesBundle\Domain\Contract\CourseCompletions;
use Phil\CoursesBundle\Application\ActivityType\Cmi5Activity;
use Phil\CoursesBundle\Application\ActivityType\LinkActivity;
use Phil\CoursesBundle\Application\ActivityType\PageActivity;
use Phil\CoursesBundle\Application\ActivityType\ScormActivity;
use Phil\CoursesBundle\Application\ActivityType\XapiActivity;
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
use Phil\CoursesBundle\Application\Contract\AssigneeMembers;
use Phil\CoursesBundle\Application\Contract\Cmi5Endpoints;
use Phil\CoursesBundle\Application\Contract\ContentStorage;
use Phil\CoursesBundle\Application\Contract\CurrentLearner;
use Phil\CoursesBundle\Application\Contract\LearningRecordStore;
use Phil\CoursesBundle\Application\Contract\TrainingNotifier;
use Phil\CoursesBundle\Application\Courses;
use Phil\CoursesBundle\Application\Enrollments;
use Phil\CoursesBundle\Application\Equivalences;
use Phil\CoursesBundle\Application\Handler\RunTrainingHandler;
use Phil\CoursesBundle\Application\Import\CourseImporter;
use Phil\CoursesBundle\Application\Import\ResultImporter;
use Phil\CoursesBundle\Application\Learning;
use Phil\CoursesBundle\Application\Message\RunTraining;
use Phil\CoursesBundle\Application\Path\LearningPaths;
use Phil\CoursesBundle\Application\Path\PathCompletions;
use Phil\CoursesBundle\Application\Scorm\ScormRuntime;
use Phil\CoursesBundle\Application\Scorm\ScormTokens;
use Phil\CoursesBundle\Application\SecondChances;
use Phil\CoursesBundle\Application\Training\Assignments;
use Phil\CoursesBundle\Application\Training\TrainingCompletions;
use Phil\CoursesBundle\Application\Training\TrainingRun;
use Phil\CoursesBundle\Application\Training\TrainingSettings;
use Phil\CoursesBundle\Application\Training\Transcripts;
use Phil\CoursesBundle\Application\Xapi\XapiLauncher;
use Phil\CoursesBundle\Application\Xapi\XapiStatements;
use Phil\CoursesBundle\Domain\Contract\AssignmentRepository;
use Phil\CoursesBundle\Domain\Contract\CourseRepository;
use Phil\CoursesBundle\Domain\Contract\EnrollmentRepository;
use Phil\CoursesBundle\Domain\Contract\EquivalenceRepository;
use Phil\CoursesBundle\Domain\Contract\LaunchSessionRepository;
use Phil\CoursesBundle\Domain\Contract\LearningPathRepository;
use Phil\CoursesBundle\Domain\Contract\RetryRequestRepository;
use Phil\CoursesBundle\Domain\Contract\TranscriptRepository;
use Phil\CoursesBundle\Domain\Event\ActivitySatisfied;
use Phil\CoursesBundle\Domain\Event\CourseCompleted;
use Phil\CoursesBundle\Domain\Event\RetryGranted;
use Phil\CoursesBundle\Domain\Event\RetryRefused;
use Phil\CoursesBundle\Domain\Event\RetryRequested;
use Phil\CoursesBundle\Domain\Event\RetryWaived;
use Phil\CoursesBundle\Infrastructure\Audit\AuditSecondChances;
use Phil\CoursesBundle\Infrastructure\Certificates\CertificatesCourseCompletions;
use Phil\CoursesBundle\Infrastructure\Console\AssignCommand;
use Phil\CoursesBundle\Infrastructure\Console\CreditsCommand;
use Phil\CoursesBundle\Infrastructure\Console\ImportCommand;
use Phil\CoursesBundle\Infrastructure\Console\ImportResultsCommand;
use Phil\CoursesBundle\Infrastructure\Console\RetriesCommand;
use Phil\CoursesBundle\Infrastructure\Console\TrainingCommand;
use Phil\CoursesBundle\Infrastructure\DependencyInjection\CoursesServices;
use Phil\CoursesBundle\Infrastructure\DependencyInjection\KitSubdirectoryPass;
use Phil\CoursesBundle\Infrastructure\Doctrine\DbalAssignmentRepository;
use Phil\CoursesBundle\Infrastructure\Doctrine\DbalCourseRepository;
use Phil\CoursesBundle\Infrastructure\Doctrine\DbalEnrollmentRepository;
use Phil\CoursesBundle\Infrastructure\Doctrine\DbalEquivalenceRepository;
use Phil\CoursesBundle\Infrastructure\Doctrine\DbalLaunchSessionRepository;
use Phil\CoursesBundle\Infrastructure\Doctrine\DbalLearningPathRepository;
use Phil\CoursesBundle\Infrastructure\Doctrine\DbalLearningRecordStore;
use Phil\CoursesBundle\Infrastructure\Doctrine\DbalRetryRequestRepository;
use Phil\CoursesBundle\Infrastructure\Doctrine\DbalScormAttemptRepository;
use Phil\CoursesBundle\Infrastructure\Doctrine\DbalTranscriptRepository;
use Phil\CoursesBundle\Infrastructure\Doctrine\SchemaListener;
use Phil\CoursesBundle\Infrastructure\Http\Cmi5Controller;
use Phil\CoursesBundle\Infrastructure\Http\ContentController;
use Phil\CoursesBundle\Infrastructure\Http\CourseController;
use Phil\CoursesBundle\Infrastructure\Http\CourseRequest;
use Phil\CoursesBundle\Infrastructure\Http\ScormController;
use Phil\CoursesBundle\Infrastructure\Http\ScormFrameController;
use Phil\CoursesBundle\Infrastructure\Http\SecondChancesController;
use Phil\CoursesBundle\Infrastructure\Http\TrainingController;
use Phil\CoursesBundle\Infrastructure\Import\ElearningXapiPackageReader;
use Phil\CoursesBundle\Infrastructure\Notifications\NotificationsTrainingNotifier;
use Phil\CoursesBundle\Infrastructure\Privacy\Cmi5Privacy;
use Phil\CoursesBundle\Infrastructure\Privacy\PathsPrivacy;
use Phil\CoursesBundle\Infrastructure\Privacy\ScormPrivacy;
use Phil\CoursesBundle\Infrastructure\Privacy\TrainingPrivacy;
use Phil\CoursesBundle\Infrastructure\Routing\RoutedCmi5Endpoints;
use Phil\CoursesBundle\Infrastructure\Scheduler\TrainingSchedule;
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
use Phil\NotificationsBundle\Application\Notifications;
use Phil\PrivacyBundle\PhilPrivacyBundle;
use Phil\SafeHttp\Limits;
use Phil\SafeHttp\SafeHttpClient;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
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
        self::assertSame([PackageLimits::ARCHIVE_BYTES, PackageLimits::ENTRIES, PackageLimits::ENTRY_BYTES, PackageLimits::TOTAL_BYTES, PackageLimits::RATIO, PackageLimits::MANIFEST_BYTES, PackageLimits::DEPTH, PackageLimits::ITEMS, PackageLimits::MANIFEST_NODES, PackageLimits::MANIFEST_DEPTH], $container->getDefinition(PackageLimits::class)->getArguments());
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
        self::assertSame(['@PhilCourses/layout.html.twig', false, true], \array_slice($container->getDefinition(CourseController::class)->getArguments(), 11, 3));
        self::assertEquals(new Reference(Equivalences::class), $container->getDefinition(CourseController::class)->getArgument(14));
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
            'import' => ['language' => 'fr', 'limits' => ['archive_bytes' => 10, 'entries' => 2, 'entry_bytes' => 3, 'total_bytes' => 4, 'ratio' => 5, 'manifest_bytes' => 6, 'depth' => 7, 'items' => 8, 'manifest_nodes' => 9, 'manifest_depth' => 11]],
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
        self::assertSame([10, 2, 3, 4, 5, 6, 7, 8, 9, 11], $container->getDefinition(PackageLimits::class)->getArguments());
        self::assertSame('fr', $container->getDefinition(CourseImporter::class)->getArgument(5));
        self::assertSame(['https://content.example.org:8443', 'https://units.example.net'], $container->getDefinition(Cmi5Controller::class)->getArgument(3));
        self::assertSame(['https://learn.example.org', 'https://learn.example.org'], \array_slice($container->getDefinition(ContentController::class)->getArguments(), 2));
        self::assertSame(['base.html.twig', true, false], \array_slice($container->getDefinition(CourseController::class)->getArguments(), 11, 3));
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
        $limits = ['archive_bytes', 'entries', 'entry_bytes', 'total_bytes', 'ratio', 'manifest_bytes', 'depth', 'items', 'manifest_nodes', 'manifest_depth'];
        $ones = $this->load([['import' => ['limits' => array_fill_keys($limits, 1)]]]);
        self::assertSame(array_fill(0, 10, 1), $ones->getDefinition(PackageLimits::class)->getArguments());
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
            XapiLauncher::class, XapiStatements::class, Assignments::class, Transcripts::class, TrainingRun::class, TrainingController::class,
            Equivalences::class, LearningPaths::class,
        ];
        $private = [
            AuditSecondChances::class, CertificatesCourseCompletions::class, Cmi5Activity::class, Cmi5Settings::class, ContentUrls::class, CourseRequest::class, CoursesExtension::class,
            DbalCourseRepository::class, DbalEnrollmentRepository::class, DbalLaunchSessionRepository::class, DbalLearningRecordStore::class,
            DbalRetryRequestRepository::class, FilesystemContentStorage::class, ImportCommand::class, ImportResultsCommand::class,
            IssueWaivedStatement::class, KitComponents::class, LinkActivity::class, PackageLimits::class, PackageReader::class, PageActivity::class,
            RetriesCommand::class, RoutedCmi5Endpoints::class, SchemaListener::class, ScormActivity::class, SecurityCurrentLearner::class,
            ZipPackageOpener::class, 'phil_courses.site_zone', DbalScormAttemptRepository::class, ScormPrivacy::class, Cmi5Privacy::class,
            XapiActivity::class, RunTrainingHandler::class, TrainingCompletions::class, TrainingSettings::class, AssignCommand::class, CreditsCommand::class,
            TrainingCommand::class, DbalAssignmentRepository::class, DbalTranscriptRepository::class, TrainingPrivacy::class, TrainingSchedule::class,
            DbalLearningPathRepository::class, DbalEquivalenceRepository::class, PathCompletions::class, PathsPrivacy::class,
            ElearningXapiPackageReader::class,
        ];
        $expected = [...array_fill_keys($public, true), ...array_fill_keys($private, false)];

        ksort($expected);
        self::assertSame($expected, $definitions);

        foreach ([CourseRepository::class, EnrollmentRepository::class, RetryRequestRepository::class, LaunchSessionRepository::class, LearningRecordStore::class, CurrentLearner::class, ContentStorage::class, AssignmentRepository::class, TranscriptRepository::class, LearningPathRepository::class, EquivalenceRepository::class] as $port) {
            self::assertTrue($container->getAlias($port)->isPublic(), $port);
        }

        self::assertEquals([new Reference(PackageLimits::class), null, new Reference(PackageOpener::class)], $container->getDefinition(PackageReader::class)->getArguments());
        self::assertEquals([new Reference(EnrollmentRepository::class), new Reference(Equivalences::class)], $container->getDefinition(Completions::class)->getArguments());
        self::assertEquals([new Reference(LearningPathRepository::class), new Reference(LearningPaths::class), new Reference(Equivalences::class)], $container->getDefinition(PathCompletions::class)->getArguments());
        self::assertSame([['event' => CourseCompleted::class]], $container->getDefinition(PathCompletions::class)->getTag('kernel.event_listener'));
        self::assertTrue($container->getDefinition(PathsPrivacy::class)->hasTag(PhilPrivacyBundle::EXPORTER_TAG));
        self::assertTrue($container->getDefinition(PathsPrivacy::class)->hasTag(PhilPrivacyBundle::ERASER_TAG));
        self::assertEquals(new Reference(Equivalences::class), $container->getDefinition(Assignments::class)->getArgument(8));
        self::assertEquals(new Reference(Equivalences::class), $container->getDefinition(Transcripts::class)->getArgument(3));
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
        self::assertEquals([new Reference(Completions::class), new Reference(LearningPaths::class)], $container->getDefinition(CertificatesCourseCompletions::class)->getArguments());
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

    public function testTheMandatoryTrainingIsWiredWithItsDefaults(): void
    {
        $container = $this->load([]);

        self::assertSame([7, 30, 10_000, 500], $container->getDefinition(TrainingSettings::class)->getArguments());
        self::assertFalse($container->hasAlias(TrainingNotifier::class), 'without the notifications bundle registered');
        self::assertEquals(new Reference(TrainingNotifier::class, ContainerInterface::NULL_ON_INVALID_REFERENCE), $container->getDefinition(Assignments::class)->getArgument(6));
        self::assertEquals(new TaggedIteratorArgument(AssigneeMembers::TAG), $container->getDefinition(Assignments::class)->getArgument(5));
        self::assertArrayHasKey(AssigneeMembers::class, $container->getAutoconfiguredInstanceof());
        self::assertSame([['event' => CourseCompleted::class]], $container->getDefinition(TrainingCompletions::class)->getTag('kernel.event_listener'));
        self::assertSame([['handles' => RunTraining::class]], $container->getDefinition(RunTrainingHandler::class)->getTag('messenger.message_handler'));
        self::assertSame(['06:00', 'UTC'], $container->getDefinition(TrainingSchedule::class)->getArguments());
        self::assertSame([['name' => 'phil_courses_training']], $container->getDefinition(TrainingSchedule::class)->getTag('scheduler.schedule_provider'));
        self::assertFalse($container->getDefinition(TrainingPrivacy::class)->getArgument(2));
        self::assertTrue($container->getDefinition(TrainingPrivacy::class)->hasTag(PhilPrivacyBundle::ERASER_TAG));
        self::assertTrue($container->getDefinition(TrainingPrivacy::class)->hasTag(PhilPrivacyBundle::EXPORTER_TAG));
        self::assertSame(['ROLE_COURSES_MANAGER', '@PhilCourses/layout.html.twig'], \array_slice($container->getDefinition(TrainingController::class)->getArguments(), 5));
        self::assertEquals(new Reference('security.authorization_checker', ContainerInterface::NULL_ON_INVALID_REFERENCE), $container->getDefinition(TrainingController::class)->getArgument(4));
        self::assertEquals(new Reference('phil_courses.site_zone'), $container->getDefinition(AssignCommand::class)->getArgument(1));

        foreach ([AssignCommand::class, CreditsCommand::class, TrainingCommand::class] as $command) {
            self::assertTrue($container->getDefinition($command)->hasTag('console.command'));
        }

        self::assertTrue($container->getDefinition(XapiActivity::class)->hasTag(ActivityType::TAG));
        self::assertEquals(new Reference(XapiStatements::class), $container->getDefinition(Cmi5Controller::class)->getArgument(4));
        self::assertEquals(new Reference(ElearningXapiPackageReader::class), $container->getDefinition(CourseImporter::class)->getArgument(6));
        self::assertEquals([new Reference(PackageLimits::class), null], $container->getDefinition(ElearningXapiPackageReader::class)->getArguments());
    }

    public function testTheMandatoryTrainingTakesItsOptionsOrIsDisabled(): void
    {
        $container = $this->load([[
            'timezone' => 'America/Toronto',
            'layout' => 'base.html.twig',
            'import' => ['xapi_reader' => 'app.xapi_reader'],
            'training' => ['reminder_days' => 90, 'renewal_grace_days' => 365, 'max_members' => 1, 'run_at' => '23:59', 'manager_role' => 'ROLE_HR', 'notifier' => 'app.notifier', 'keep_transcripts' => true],
        ]], ['PhilNotificationsBundle']);

        self::assertSame([90, 365, 1, 500], $container->getDefinition(TrainingSettings::class)->getArguments());
        self::assertSame(['23:59', 'America/Toronto'], $container->getDefinition(TrainingSchedule::class)->getArguments());
        self::assertSame('app.notifier', (string) $container->getAlias(TrainingNotifier::class));
        self::assertFalse($container->hasDefinition(NotificationsTrainingNotifier::class));
        self::assertTrue($container->getDefinition(TrainingPrivacy::class)->getArgument(2));
        self::assertSame(['ROLE_HR', 'base.html.twig'], \array_slice($container->getDefinition(TrainingController::class)->getArguments(), 5));
        self::assertEquals(new Reference('app.xapi_reader'), $container->getDefinition(CourseImporter::class)->getArgument(6));

        $notified = $this->load([], ['PhilNotificationsBundle']);
        self::assertSame(NotificationsTrainingNotifier::class, (string) $notified->getAlias(TrainingNotifier::class));
        self::assertEquals([new Reference(Notifications::class)], $notified->getDefinition(NotificationsTrainingNotifier::class)->getArguments());

        self::assertFalse($this->load([['training' => ['schedule' => false]]])->hasDefinition(TrainingSchedule::class));

        $disabled = $this->load([['training' => false]]);

        foreach ([TrainingSettings::class, Assignments::class, Transcripts::class, TrainingRun::class, TrainingCompletions::class, RunTrainingHandler::class, TrainingSchedule::class, AssignCommand::class, TrainingPrivacy::class, TrainingController::class] as $service) {
            self::assertFalse($disabled->hasDefinition($service), $service);
        }

        self::assertSame([1, 1], \array_slice($this->load([['training' => ['reminder_days' => 1, 'renewal_grace_days' => 1]]])->getDefinition(TrainingSettings::class)->getArguments(), 0, 2));

        foreach ([['training' => ['reminder_days' => 0]], ['training' => ['reminder_days' => 91]], ['training' => ['renewal_grace_days' => 0]], ['training' => ['renewal_grace_days' => 366]], ['training' => ['max_members' => 0]], ['training' => ['run_at' => '24:00']], ['training' => ['run_at' => '6:00']], ['training' => ['run_at' => "06:00\n"]], ['training' => ['run_at' => 'x06:00']], ['training' => ['run_at' => '06:001']], ['training' => ['run_at' => 600]]] as $config) {
            try {
                $this->load([$config]);
                self::fail('The option is accepted: '.json_encode($config));
            } catch (InvalidConfigurationException $invalidConfigurationException) {
                self::assertNotSame('', $invalidConfigurationException->getMessage());
            }
        }
    }

    public function testTheTypesOfTheTrainingAreDeclaredToTheNotifications(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new class extends Extension {
            #[Override]
            public function load(array $configs, ContainerBuilder $container): void
            {
            }

            #[Override]
            public function getAlias(): string
            {
                return 'phil_notifications';
            }
        });
        $extension = $this->extension();
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $extension->prepend($container);

        self::assertSame([['types' => NotificationsTrainingNotifier::types()]], $container->getExtensionConfig('phil_notifications'));
    }

    public function testTheNotificationsOfTheTrainingAreTranslatedInBothLanguages(): void
    {
        $english = require \dirname(__DIR__, 2).'/translations/phil_notifications.en.php';
        $french = require \dirname(__DIR__, 2).'/translations/phil_notifications.fr.php';

        self::assertIsArray($english);
        self::assertIsArray($french);
        self::assertSame(array_keys($english), array_keys($french));

        foreach (array_keys(NotificationsTrainingNotifier::types()) as $type) {
            self::assertArrayHasKey($type.'.title', $english);
            self::assertArrayHasKey($type.'.body', $english);
        }
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

<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Infrastructure\DependencyInjection;

use DateTimeZone;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use LogicException;
use Phil\AuditBundle\Application\AuditTrail;
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
use Phil\CoursesBundle\Domain\Contract\ScormAttemptRepository;
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
use Phil\ElearningPackages\Archive\ZipPackageOpener;
use Phil\ElearningPackages\Contract\PackageOpener;
use Phil\ElearningPackages\PackageLimits;
use Phil\ElearningPackages\PackageReader;
use Phil\PrivacyBundle\Application\Contract\PersonalDataEraser;
use Phil\PrivacyBundle\PhilPrivacyBundle;
use Phil\SafeHttp\Limits;
use Phil\SafeHttp\SafeHttpClient;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;

/**
 * The bundle's services: the DBAL stores, the activity types, the entry points, the import, cmi5 and its learning
 * record store — inside the bundle, the application's, or a remote one through phil/safe-http —, the console commands,
 * the pages, the administration page of the second chances with Symfony Security, and their Twig function; the SCORM
 * run time, its commit endpoint and the frame of its API; the schema listener with the ORM, the trail of the second
 * chances with phil/audit-bundle, and the SCORM attempts in the access and erasure requests of phil/privacy-bundle.
 *
 * @phpstan-type Config array{
 *     connection: string,
 *     timezone: string,
 *     current_learner: ?string,
 *     layout: string,
 *     kit_subdirectory: ?string,
 *     self_enrollment: bool,
 *     locked_activities: 'show'|'hide',
 *     storage: array{directory: string, service: ?string},
 *     content: array{base_url: ?string, application_origin: ?string, allow_localhost: bool},
 *     import: array{language: ?string, limits: array{archive_bytes: int, entries: int, entry_bytes: int, total_bytes: int, ratio: int, manifest_bytes: int, depth: int, items: int}},
 *     cmi5: array{actor_home_page: ?string, session_ttl: int, secret: string, allowed_origins: list<string>, lrs: ?string, remote_lrs: array{enabled: bool, endpoint: ?string, username: ?string, password: ?string, version: string, timeout: float, max_duration: float}},
 *     scorm: array{enabled: bool, token_ttl: int, secret: string},
 *     admin: array{role: string},
 *     audit: array{enabled: bool, segment: string}
 * }
 */
final class CoursesServices
{
    /**
     * @param Config          $config
     * @param list<array-key> $bundles the names of the registered bundles
     */
    public static function register(ContainerBuilder $container, array $config, array $bundles): void
    {
        $connection = new Reference('doctrine.dbal.'.$config['connection'].'_connection');
        $clock = new Reference('clock');
        $events = new Reference('event_dispatcher', ContainerInterface::NULL_ON_INVALID_REFERENCE);

        self::stores($container, $connection, $clock, $config);
        self::ports($container, $config);

        $container->register('phil_courses.site_zone', DateTimeZone::class)->setArguments([$config['timezone']]);
        $container->register(ContentUrls::class, ContentUrls::class)->setArguments([$config['content']['base_url'], $config['content']['allow_localhost']]);
        $container->register(Cmi5Settings::class, Cmi5Settings::class)->setArguments([$config['cmi5']['actor_home_page'], $config['cmi5']['session_ttl'], $config['content']['allow_localhost']]);

        self::scorm($container, $clock, $events, $config);

        foreach ([PageActivity::class => [], LinkActivity::class => [], ScormActivity::class => [new Reference(ContentUrls::class), $config['scorm']['enabled'] ? new Reference(ScormRuntime::class) : null], Cmi5Activity::class => [new Reference(Cmi5Launcher::class)]] as $type => $arguments) {
            $container->register($type, $type)->setArguments($arguments)->addTag(ActivityType::TAG);
        }

        $container->registerForAutoconfiguration(ActivityType::class)->addTag(ActivityType::TAG);
        $container->register(ActivityTypes::class, ActivityTypes::class)->setArguments([new TaggedIteratorArgument(ActivityType::TAG)])->setPublic(true);

        self::service($container, Courses::class, [new Reference(CourseRepository::class), new Reference(ActivityTypes::class), $clock]);
        self::service($container, Enrollments::class, [new Reference(CourseRepository::class), new Reference(EnrollmentRepository::class), $clock, $events]);
        self::service($container, Availabilities::class, [new Reference(CourseRepository::class), new Reference(EnrollmentRepository::class), new Reference(RetryRequestRepository::class), $clock, new Reference('phil_courses.site_zone')]);
        self::service($container, Learning::class, [new Reference(Enrollments::class), new Reference(Availabilities::class), new Reference(ActivityTypes::class)]);
        self::service($container, Completions::class, [new Reference(EnrollmentRepository::class)]);

        if (interface_exists(CourseCompletions::class)) {
            $container->register(CertificatesCourseCompletions::class, CertificatesCourseCompletions::class)->setArguments([new Reference(Completions::class)]);
            $container->setAlias(CourseCompletions::class, CertificatesCourseCompletions::class);
        }

        self::service($container, SecondChances::class, [new Reference(RetryRequestRepository::class), new Reference(Courses::class), new Reference(Enrollments::class), new Reference(Availabilities::class), $clock, $events]);

        $limits = $config['import']['limits'];
        $container->register(PackageLimits::class, PackageLimits::class)->setArguments([$limits['archive_bytes'], $limits['entries'], $limits['entry_bytes'], $limits['total_bytes'], $limits['ratio'], $limits['manifest_bytes'], $limits['depth'], $limits['items']]);
        $container->register(ZipPackageOpener::class, ZipPackageOpener::class);
        $container->setAlias(PackageOpener::class, ZipPackageOpener::class);
        $container->register(PackageReader::class, PackageReader::class)->setArguments([new Reference(PackageLimits::class), $config['import']['language'], new Reference(PackageOpener::class)]);
        self::service($container, CourseImporter::class, [new Reference(PackageOpener::class), new Reference(Courses::class), new Reference(ContentStorage::class), new Reference(PackageLimits::class), $events, $config['import']['language']]);
        self::service($container, ResultImporter::class, [new Reference(Courses::class), new Reference(Enrollments::class)]);

        self::cmi5($container, $clock, $events, $config);

        if (class_exists(Command::class)) {
            $container->register(ImportCommand::class, ImportCommand::class)->setArguments([new Reference(CourseImporter::class)])->addTag('console.command');
            $container->register(ImportResultsCommand::class, ImportResultsCommand::class)->setArguments([new Reference(ResultImporter::class)])->addTag('console.command');
            $container->register(RetriesCommand::class, RetriesCommand::class)->setArguments([new Reference(SecondChances::class)])->addTag('console.command');
        }

        if (class_exists(GenerateSchemaEventArgs::class)) {
            $container->register(SchemaListener::class, SchemaListener::class)
                ->setArguments([$connection])
                ->addTag('doctrine.event_listener', ['event' => 'postGenerateSchema', 'connection' => $config['connection']]);
        }

        if ($config['audit']['enabled'] && class_exists(AuditTrail::class) && \in_array('PhilAuditBundle', $bundles, true)) {
            $container->register(AuditSecondChances::class, AuditSecondChances::class)
                ->setArguments([new Reference(AuditTrail::class), $config['audit']['segment']])
                ->addTag('kernel.event_listener', ['event' => RetryRequested::class, 'method' => 'requested'])
                ->addTag('kernel.event_listener', ['event' => RetryGranted::class, 'method' => 'granted'])
                ->addTag('kernel.event_listener', ['event' => RetryRefused::class, 'method' => 'refused'])
                ->addTag('kernel.event_listener', ['event' => RetryWaived::class, 'method' => 'waived']);
        }

        self::pages($container, $config);
    }

    /**
     * @param Config $config
     */
    private static function stores(ContainerBuilder $container, Reference $connection, Reference $clock, array $config): void
    {
        foreach ([
            CourseRepository::class => DbalCourseRepository::class,
            EnrollmentRepository::class => DbalEnrollmentRepository::class,
            RetryRequestRepository::class => DbalRetryRequestRepository::class,
            LaunchSessionRepository::class => DbalLaunchSessionRepository::class,
            ScormAttemptRepository::class => DbalScormAttemptRepository::class,
        ] as $port => $store) {
            $container->register($store, $store)->setArguments([$connection]);
            $container->setAlias($port, $store)->setPublic(true);
        }

        $container->register(DbalLearningRecordStore::class, DbalLearningRecordStore::class)->setArguments([$connection, $clock]);
        $container->setAlias(LearningRecordStore::class, $config['cmi5']['lrs'] ?? self::remoteStore($container, $config['cmi5']['remote_lrs']) ?? DbalLearningRecordStore::class)->setPublic(true);
    }

    /**
     * The remote learning record store, when it is enabled: its own guarded client, reset between requests.
     *
     * @param array{enabled: bool, endpoint: ?string, username: ?string, password: ?string, version: string, timeout: float, max_duration: float} $remote
     *
     * @return ?string the service of the store, null when it is not enabled
     */
    private static function remoteStore(ContainerBuilder $container, array $remote): ?string
    {
        if (!$remote['enabled']) {
            return null;
        }

        if (!class_exists(SafeHttpClient::class)) {
            throw new LogicException('The remote learning record store sends the statements through phil/safe-http: run "composer require phil/safe-http".');
        }

        $container->register('phil_courses.lrs_client', SafeHttpClient::class)
            ->setArguments([null, new Definition(Limits::class, [$remote['timeout'], $remote['max_duration'], 65_536, 0])])
            ->addTag('kernel.reset', ['method' => 'reset']);
        $container->register(HttpLearningRecordStore::class, HttpLearningRecordStore::class)->setArguments([
            new Reference('phil_courses.lrs_client'),
            $remote['endpoint'],
            new Reference(DbalLearningRecordStore::class),
            $remote['username'],
            $remote['password'],
            $remote['version'],
            new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE),
        ]);

        return HttpLearningRecordStore::class;
    }

    /**
     * @param Config $config
     */
    private static function ports(ContainerBuilder $container, array $config): void
    {
        if (null === $config['current_learner']) {
            $container->register(SecurityCurrentLearner::class, SecurityCurrentLearner::class)
                ->setArguments([new Reference('security.token_storage', ContainerInterface::NULL_ON_INVALID_REFERENCE)]);
        }

        $container->setAlias(CurrentLearner::class, $config['current_learner'] ?? SecurityCurrentLearner::class)->setPublic(true);

        if (null === $config['storage']['service']) {
            $container->register(FilesystemContentStorage::class, FilesystemContentStorage::class)->setArguments([$config['storage']['directory']]);
        }

        $container->setAlias(ContentStorage::class, $config['storage']['service'] ?? FilesystemContentStorage::class)->setPublic(true);
    }

    /**
     * @param Config $config
     */
    private static function cmi5(ContainerBuilder $container, Reference $clock, Reference $events, array $config): void
    {
        self::service($container, Cmi5Statements::class, [
            new Reference(LearningRecordStore::class),
            new Reference(LaunchSessionRepository::class),
            new Reference(EnrollmentRepository::class),
            new Reference(CourseRepository::class),
            new Reference(Enrollments::class),
            new Reference(Cmi5Settings::class),
            $clock,
            $events,
        ]);
        self::service($container, Cmi5Tokens::class, [new Reference(LaunchSessionRepository::class), $clock, $config['cmi5']['secret']]);
        $container->register(IssueWaivedStatement::class, IssueWaivedStatement::class)
            ->setArguments([new Reference(EnrollmentRepository::class), new Reference(CourseRepository::class), new Reference(Cmi5Statements::class), new Reference(Cmi5Settings::class), $clock])
            ->addTag('kernel.event_listener', ['event' => ActivitySatisfied::class]);

        $container->register(RoutedCmi5Endpoints::class, RoutedCmi5Endpoints::class)->setArguments([new Reference('router')]);
        $container->setAlias(Cmi5Endpoints::class, RoutedCmi5Endpoints::class);

        self::service($container, Cmi5Launcher::class, [
            new Reference(LaunchSessionRepository::class),
            new Reference(Cmi5Statements::class),
            new Reference(Cmi5Endpoints::class),
            new Reference(ContentUrls::class),
            new Reference(Cmi5Settings::class),
            $clock,
        ]);

        $origin = null === $config['content']['base_url'] ? [] : [self::origin($config['content']['base_url'])];
        $container->register(Cmi5Controller::class, Cmi5Controller::class)
            ->setArguments([new Reference(Cmi5Tokens::class), new Reference(Cmi5Statements::class), new Reference(LearningRecordStore::class), array_values(array_unique([...$origin, ...$config['cmi5']['allowed_origins']]))])
            ->addTag('controller.service_arguments')
            ->setPublic(true);
    }

    /**
     * The SCORM run time, its tokens and its privacy module when phil/privacy-bundle is installed.
     *
     * @param Config $config
     */
    private static function scorm(ContainerBuilder $container, Reference $clock, Reference $events, array $config): void
    {
        self::service($container, ScormTokens::class, [$clock, $config['scorm']['secret'], $config['scorm']['token_ttl']]);
        self::service($container, ScormRuntime::class, [
            new Reference(ScormAttemptRepository::class),
            new Reference(CourseRepository::class),
            new Reference(EnrollmentRepository::class),
            new Reference(Enrollments::class),
            new Reference(ScormTokens::class),
            $clock,
            $events,
        ]);

        if (interface_exists(PersonalDataEraser::class)) {
            $container->register(ScormPrivacy::class, ScormPrivacy::class)
                ->setArguments([new Reference(EnrollmentRepository::class), new Reference(ScormAttemptRepository::class)])
                ->addTag(PhilPrivacyBundle::EXPORTER_TAG)
                ->addTag(PhilPrivacyBundle::ERASER_TAG);
        }
    }

    /**
     * The origin of the site that frames the content.
     *
     * @param Config $config
     */
    private static function application(array $config): ?string
    {
        $origin = $config['content']['application_origin'] ?? $config['cmi5']['actor_home_page'];

        return null === $origin ? null : self::origin($origin);
    }

    /**
     * @param Config $config
     */
    private static function pages(ContainerBuilder $container, array $config): void
    {
        $container->register(ContentController::class, ContentController::class)
            ->setArguments([
                new Reference(ContentStorage::class),
                new Reference(ContentUrls::class),
                self::application($config),
                null === $config['cmi5']['actor_home_page'] ? null : self::origin($config['cmi5']['actor_home_page']),
            ])
            ->addTag('controller.service_arguments')
            ->setPublic(true);
        $container->register(ScormFrameController::class, ScormFrameController::class)
            ->setArguments([new Reference(ContentUrls::class), self::application($config), null])
            ->addTag('controller.service_arguments')
            ->setPublic(true);

        if (!class_exists(Environment::class) || !interface_exists(CsrfTokenManagerInterface::class)) {
            return;
        }

        if (interface_exists(AuthorizationCheckerInterface::class)) {
            $container->register(SecondChancesController::class, SecondChancesController::class)
                ->setArguments([
                    new Reference(SecondChances::class),
                    new Reference(CurrentLearner::class),
                    new Reference(CourseRequest::class),
                    new Reference('twig'),
                    new Reference('router'),
                    new Reference('security.authorization_checker', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                    $config['admin']['role'],
                    $config['layout'],
                ])
                ->addTag('controller.service_arguments')
                ->setPublic(true);
        }

        $container->register(KitComponents::class, KitComponents::class)->setArguments([$config['kit_subdirectory']]);
        $container->register(CoursesExtension::class, CoursesExtension::class)
            ->setArguments([new Reference(KitComponents::class)])
            ->addTag('twig.attribute_extension')
            ->addTag('twig.runtime');
        $container->register(CourseRequest::class, CourseRequest::class)->setArguments([new Reference('security.csrf.token_manager')]);
        $container->register(CourseController::class, CourseController::class)
            ->setArguments([
                new Reference(Courses::class),
                new Reference(Enrollments::class),
                new Reference(Availabilities::class),
                new Reference(Learning::class),
                new Reference(SecondChances::class),
                new Reference(ActivityTypes::class),
                new Reference(CurrentLearner::class),
                new Reference(CourseRequest::class),
                new Reference(ContentUrls::class),
                new Reference('twig'),
                new Reference('router'),
                $config['layout'],
                $config['self_enrollment'],
                'show' === $config['locked_activities'],
            ])
            ->addTag('controller.service_arguments')
            ->setPublic(true);
        $container->register(ScormController::class, ScormController::class)
            ->setArguments([new Reference(ScormRuntime::class), new Reference(CourseRequest::class), new Reference(CurrentLearner::class)])
            ->addTag('controller.service_arguments')
            ->setPublic(true);
    }

    /**
     * @param list<mixed> $arguments
     */
    private static function service(ContainerBuilder $container, string $class, array $arguments): Definition
    {
        return $container->register($class, $class)->setArguments($arguments)->setPublic(true);
    }

    /**
     * The scheme, host and port of an address the configuration checked already.
     */
    private static function origin(string $url): string
    {
        return 1 === preg_match('#^https?://[^/]+#i', $url, $match) ? strtolower($match[0]) : $url;
    }
}

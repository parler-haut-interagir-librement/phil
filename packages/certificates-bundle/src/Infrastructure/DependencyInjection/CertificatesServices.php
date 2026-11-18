<?php

declare(strict_types=1);

namespace Phil\CertificatesBundle\Infrastructure\DependencyInjection;

use Phil\CertificatesBundle\Application\Awarding;
use Phil\CertificatesBundle\Application\CertificatePdf;
use Phil\CertificatesBundle\Application\CertificateStatusLists;
use Phil\CertificatesBundle\Application\ContinuingEducation;
use Phil\CertificatesBundle\Application\Contract\CertificateUrls;
use Phil\CertificatesBundle\Application\Courses;
use Phil\CertificatesBundle\Application\ExpiryReminders;
use Phil\CertificatesBundle\Application\Revocation;
use Phil\CertificatesBundle\Application\StatusListLayout;
use Phil\CertificatesBundle\Domain\Contract\CertificateRepository;
use Phil\CertificatesBundle\Domain\Contract\CourseCompletions;
use Phil\CertificatesBundle\Domain\Contract\CourseRepository;
use Phil\CertificatesBundle\Domain\Contract\LearningActivityRepository;
use Phil\CertificatesBundle\Infrastructure\Console\AwardCommand;
use Phil\CertificatesBundle\Infrastructure\Console\CourseCommand;
use Phil\CertificatesBundle\Infrastructure\Console\CreditCommand;
use Phil\CertificatesBundle\Infrastructure\Console\CreditsCommand;
use Phil\CertificatesBundle\Infrastructure\Console\ExportCommand;
use Phil\CertificatesBundle\Infrastructure\Console\RecertifyCommand;
use Phil\CertificatesBundle\Infrastructure\Console\RemindCommand;
use Phil\CertificatesBundle\Infrastructure\Console\RevokeCommand;
use Phil\CertificatesBundle\Infrastructure\Console\StatusCommand;
use Phil\CertificatesBundle\Infrastructure\Doctrine\BinaryKeysListener;
use Phil\CertificatesBundle\Infrastructure\Doctrine\DoctrineCertificateRepository;
use Phil\CertificatesBundle\Infrastructure\Doctrine\DoctrineCourseRepository;
use Phil\CertificatesBundle\Infrastructure\Doctrine\DoctrineLearningActivityRepository;
use Phil\CertificatesBundle\Infrastructure\Http\RegisterController;
use Phil\CertificatesBundle\Infrastructure\Routing\RoutedCertificateUrls;
use Phil\OpenBadges\CredentialIssuer;
use Phil\OpenBadges\Issuer;
use Phil\OpenBadgesBundle\Application\Securing;
use Phil\OpenBadgesBundle\Infrastructure\Routing\StatusListUrls;
use Phil\PagedMedia\Font\StandardFont;
use Phil\PagedMedia\Font\TrueTypeFont;
use Phil\PagedMedia\Pdf\PageSize;
use Phil\PagedMediaBundle\Application\PagedMedia;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * The bundle's services: the courses, the awarding, the recertification and the revocation of certificates, the
 * reminders of their end, the credits of continuing education, their status lists, their PDF and their URLs; the
 * Doctrine repositories when DoctrineBundle is registered; the source of completions the application registers under
 * `CourseCompletions`, when it does; the register's controller when Twig is; the console
 * commands when the Console component is installed. None keeps anything from one request to the next.
 */
final class CertificatesServices
{
    public const string DEFAULT_LOCK_FACTORY = 'lock.factory';

    private function __construct()
    {
    }

    /**
     * @param array{clock: string, lock_factory: ?string, reminders: array{days: list<int>}, status_lists: array{prefix: string, size: int, others: ?string}, pdf: array{page_size: string, font: ?string, bold_font: ?string, bake: bool, archival: bool, sign: bool}, register: array{enabled: bool, layout: string}} $config
     */
    public static function register(ContainerBuilder $container, array $config, bool $doctrine, bool $console): void
    {
        $clock = new Reference($config['clock']);
        $certificates = new Reference(CertificateRepository::class);
        $courses = new Reference(CourseRepository::class);
        $urls = new Reference(CertificateUrls::class);
        $activities = new Reference(LearningActivityRepository::class, ContainerInterface::NULL_ON_INVALID_REFERENCE);
        $events = new Reference('event_dispatcher', ContainerInterface::NULL_ON_INVALID_REFERENCE);

        if ($doctrine) {
            $container->register(DoctrineCourseRepository::class, DoctrineCourseRepository::class)->setArguments([new Reference('doctrine.orm.entity_manager')]);
            $container->setAlias(CourseRepository::class, DoctrineCourseRepository::class);
            $container->register(DoctrineCertificateRepository::class, DoctrineCertificateRepository::class)->setArguments([new Reference('doctrine.orm.entity_manager'), new Reference('doctrine')]);
            $container->setAlias(CertificateRepository::class, DoctrineCertificateRepository::class);
            $container->register(DoctrineLearningActivityRepository::class, DoctrineLearningActivityRepository::class)->setArguments([new Reference('doctrine.orm.entity_manager')]);
            $container->setAlias(LearningActivityRepository::class, DoctrineLearningActivityRepository::class);
            $container->register(BinaryKeysListener::class, BinaryKeysListener::class)
                ->addTag('doctrine.event_listener', ['event' => 'loadClassMetadata']);
        }

        $container->register(StatusListLayout::class, StatusListLayout::class)
            ->setArguments([$config['status_lists']['prefix'], $config['status_lists']['size']]);
        $container->register(CertificateStatusLists::class, CertificateStatusLists::class)
            ->setArguments([$certificates, new Reference(StatusListLayout::class), null === $config['status_lists']['others'] ? null : new Reference($config['status_lists']['others'])]);

        $container->register(RoutedCertificateUrls::class, RoutedCertificateUrls::class)
            ->setArguments([new Reference('router'), new Reference(StatusListUrls::class)]);
        $container->setAlias(CertificateUrls::class, RoutedCertificateUrls::class);

        $container->register(Courses::class, Courses::class)->setArguments([$courses]);
        $container->register(Awarding::class, Awarding::class)
            ->setArguments([new Reference(Courses::class), $certificates, new Reference(CredentialIssuer::class), new Reference(Securing::class), $urls, $clock, new Reference(StatusListLayout::class), self::locks($config['lock_factory']), $events, $activities, new Reference(CourseCompletions::class, ContainerInterface::NULL_ON_INVALID_REFERENCE)]);
        $container->register(Revocation::class, Revocation::class)->setArguments([$certificates, $clock, $events]);
        $container->register(ExpiryReminders::class, ExpiryReminders::class)->setArguments([$certificates, $clock, new Reference('event_dispatcher'), $config['reminders']['days']]);
        $container->register(ContinuingEducation::class, ContinuingEducation::class)
            ->setArguments([new Reference(LearningActivityRepository::class), new Reference(Courses::class), $certificates, $clock, $events]);

        if ($console) {
            $container->register(CourseCommand::class, CourseCommand::class)->setArguments([new Reference(Courses::class), $urls])->addTag('console.command');
            $container->register(AwardCommand::class, AwardCommand::class)->setArguments([new Reference(Awarding::class), $urls])->addTag('console.command');
            $container->register(RevokeCommand::class, RevokeCommand::class)->setArguments([new Reference(Revocation::class)])->addTag('console.command');
            $container->register(StatusCommand::class, StatusCommand::class)->setArguments([$certificates, $clock])->addTag('console.command');
            $container->register(ExportCommand::class, ExportCommand::class)->setArguments([$certificates])->addTag('console.command');
            $container->register(RecertifyCommand::class, RecertifyCommand::class)->setArguments([new Reference(Awarding::class), $urls])->addTag('console.command');
            $container->register(RemindCommand::class, RemindCommand::class)->setArguments([new Reference(ExpiryReminders::class)])->addTag('console.command');
            $container->register(CreditCommand::class, CreditCommand::class)->setArguments([new Reference(ContinuingEducation::class)])->addTag('console.command');
            $container->register(CreditsCommand::class, CreditsCommand::class)->setArguments([new Reference(ContinuingEducation::class), $clock])->addTag('console.command');
        }

        $container->register(CertificatePdf::class, CertificatePdf::class)->setArguments([
            new Reference(PagedMedia::class),
            $urls,
            new Reference('translator'),
            new Reference(Issuer::class),
            new Definition(PageSize::class)->setFactory([PageSize::class, $config['pdf']['page_size']]),
            self::font($config['pdf']['font'], StandardFont::Helvetica),
            self::font($config['pdf']['bold_font'], StandardFont::HelveticaBold),
            $config['pdf']['bake'],
            $config['pdf']['sign'],
            $config['pdf']['archival'],
        ]);

        if (!$config['register']['enabled']) {
            return;
        }

        $container->register(RegisterController::class, RegisterController::class)
            ->setArguments([$courses, $certificates, new Reference(CertificatePdf::class), new Reference('twig'), new Reference('router'), $clock, $config['register']['layout']])
            ->addTag('controller.service_arguments');
    }

    /**
     * The lock factory the status indexes are taken under: the one FrameworkBundle registers when there is one, or the
     * one the configuration names, which must exist.
     */
    private static function locks(?string $service): ?Reference
    {
        return match ($service) {
            null => null,
            self::DEFAULT_LOCK_FACTORY => new Reference($service, ContainerInterface::NULL_ON_INVALID_REFERENCE),
            default => new Reference($service),
        };
    }

    private static function font(?string $path, StandardFont $standard): Definition|StandardFont
    {
        return null === $path ? $standard : new Definition(TrueTypeFont::class)->setFactory([TrueTypeFont::class, 'fromFile'])->setArguments([$path]);
    }
}

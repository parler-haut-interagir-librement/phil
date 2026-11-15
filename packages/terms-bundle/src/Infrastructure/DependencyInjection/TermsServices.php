<?php

declare(strict_types=1);

namespace Phil\TermsBundle\Infrastructure\DependencyInjection;

use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Phil\IpAnonymizer\Truncation;
use Phil\LegalNoticesBundle\Infrastructure\Twig\FooterLinks;
use Phil\NotificationsBundle\Application\Notifications;
use Phil\PrivacyBundle\Application\Contract\PersonalDataEraser;
use Phil\TermsBundle\Application\AcceptanceChecker;
use Phil\TermsBundle\Application\Contract\AddressMasker;
use Phil\TermsBundle\Application\Contract\MemberResolver;
use Phil\TermsBundle\Application\Contract\MemberSource;
use Phil\TermsBundle\Application\Contract\PolicyTexts;
use Phil\TermsBundle\Application\Contract\TermsNotifier;
use Phil\TermsBundle\Application\ContributionLicences;
use Phil\TermsBundle\Application\Handler\SendTermsRemindersHandler;
use Phil\TermsBundle\Application\Message\SendTermsReminders;
use Phil\TermsBundle\Application\Outstanding;
use Phil\TermsBundle\Application\Policies;
use Phil\TermsBundle\Application\Terms;
use Phil\TermsBundle\Application\VersionCatalog;
use Phil\TermsBundle\Domain\Contract\AcceptanceRepository;
use Phil\TermsBundle\Domain\Contract\PolicyVersionRepository;
use Phil\TermsBundle\Domain\Exception\InvalidTerms;
use Phil\TermsBundle\Domain\Identifiers;
use Phil\TermsBundle\Domain\ValueObject\Policy;
use Phil\TermsBundle\Infrastructure\Console\LegalLinksCommand;
use Phil\TermsBundle\Infrastructure\Console\OutstandingCommand;
use Phil\TermsBundle\Infrastructure\Doctrine\DbalAcceptances;
use Phil\TermsBundle\Infrastructure\Doctrine\DbalPolicyVersions;
use Phil\TermsBundle\Infrastructure\Doctrine\TermsSchemaListener;
use Phil\TermsBundle\Infrastructure\Form\SignupAcceptances;
use Phil\TermsBundle\Infrastructure\Form\TermsAcceptanceTypeExtension;
use Phil\TermsBundle\Infrastructure\Http\AcceptanceController;
use Phil\TermsBundle\Infrastructure\Http\AcceptanceGate;
use Phil\TermsBundle\Infrastructure\Http\Pages;
use Phil\TermsBundle\Infrastructure\Http\PolicyController;
use Phil\TermsBundle\Infrastructure\Http\TermsAdminController;
use Phil\TermsBundle\Infrastructure\IpAnonymizer\NetworkMasker;
use Phil\TermsBundle\Infrastructure\Notifications\NotificationsNotifier;
use Phil\TermsBundle\Infrastructure\Notifications\OutstandingAudience;
use Phil\TermsBundle\Infrastructure\Privacy\AcceptancesPrivacy;
use Phil\TermsBundle\Infrastructure\Scheduler\TermsSchedule;
use Phil\TermsBundle\Infrastructure\Security\TokenMemberResolver;
use Phil\TermsBundle\Infrastructure\Text\PolicyTextRenderer;
use Phil\TermsBundle\Infrastructure\Twig\KitComponents;
use Phil\TermsBundle\Infrastructure\Twig\TermsTwig;
use Phil\TextDiff\TextDiff;
use Phil\Ujo\Ujo;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

/**
 * The services of the configuration. Each policy and each locale is checked here, so that a bad one fails the
 * container build rather than the first request; an option that needs a package which is not installed fails it too.
 *
 * @phpstan-type Config array{
 *     connection: string,
 *     clock: string,
 *     default_locale: string,
 *     locales: list<string>,
 *     timezone: string,
 *     kit_subdirectory: string|null,
 *     policies: array<array-key, array{required: bool, renew_after_days: int|null, contribution_licence: bool}>,
 *     member_resolver: string|null,
 *     stable_identifiers: bool,
 *     member_source: string,
 *     notifier: string|null,
 *     gate: array{enabled: bool, exempt_routes: list<string>, exempt_paths: list<string>, api_paths: list<string>, problem_type: string},
 *     network: array{enabled: bool, ipv4_prefix: int, ipv6_prefix: int},
 *     pages: array{layout: string},
 *     admin: array{enabled: bool, role: string, layout: string},
 *     notifications: array{enabled: bool, published_type: string, reminder_type: string},
 *     reminders: array{enabled: bool, frequency: string, days_ahead: int},
 * }
 */
final class TermsServices
{
    public const string AUDIENCE_TAG = 'phil_notifications.audience_provider';

    public const string EXPORTER_TAG = 'phil_privacy.exporter';

    public const string ERASER_TAG = 'phil_privacy.eraser';

    public const string LEGAL_NOTICES_BUNDLE = 'PhilLegalNoticesBundle';

    private function __construct()
    {
    }

    /**
     * @param Config $config
     */
    public static function register(ContainerBuilder $container, array $config): void
    {
        $connection = new Reference(\sprintf('doctrine.dbal.%s_connection', $config['connection']));
        $clock = new Reference($config['clock']);
        $locales = self::locales($config['default_locale'], $config['locales']);

        $container->register(Policies::class, Policies::class)->setArguments(self::policies($config['policies']));
        $container->register(DbalPolicyVersions::class, DbalPolicyVersions::class)->setArguments([$connection]);
        $container->setAlias(PolicyVersionRepository::class, DbalPolicyVersions::class)->setPublic(true);
        $container->register(DbalAcceptances::class, DbalAcceptances::class)->setArguments([$connection]);
        $container->setAlias(AcceptanceRepository::class, DbalAcceptances::class)->setPublic(true);
        // The bundle reads and writes through DBAL alone; the ORM, when it is there, only learns of the two tables.
        if (class_exists(GenerateSchemaEventArgs::class)) {
            $container->register(TermsSchemaListener::class, TermsSchemaListener::class)
                ->setArguments([$connection])
                ->addTag('doctrine.event_listener', ['event' => 'postGenerateSchema', 'connection' => $config['connection']]);
        }

        // Kept for the request and dropped by `kernel.reset`: a worker never serves a request with another's versions.
        $container->register(VersionCatalog::class, VersionCatalog::class)
            ->setArguments([new Reference(PolicyVersionRepository::class)])
            ->addTag('kernel.reset', ['method' => 'reset'])
            ->setPublic(true);
        $container->register(PolicyTextRenderer::class, PolicyTextRenderer::class)->setArguments([
            class_exists(Ujo::class) ? new Definition(Ujo::class) : null,
            class_exists(TextDiff::class) ? new Definition(TextDiff::class) : null,
        ]);
        $container->setAlias(PolicyTexts::class, PolicyTextRenderer::class);

        self::members($container, $config);
        $notifier = self::notifier($container, $config);

        $container->register(AcceptanceChecker::class, AcceptanceChecker::class)
            ->setArguments([new Reference(Policies::class), new Reference(VersionCatalog::class), new Reference(AcceptanceRepository::class)])
            ->setPublic(true);
        $container->register(Terms::class, Terms::class)
            ->setArguments([
                new Reference(Policies::class),
                new Reference(VersionCatalog::class),
                new Reference(PolicyVersionRepository::class),
                new Reference(PolicyTexts::class),
                new Reference(AcceptanceRepository::class),
                $clock,
                self::masker($container, $config['network']),
                $notifier,
                new Reference('event_dispatcher', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])
            ->setPublic(true);
        $container->register(ContributionLicences::class, ContributionLicences::class)
            ->setArguments([new Reference(Policies::class), new Reference(VersionCatalog::class), new Reference(AcceptanceRepository::class)])
            ->setPublic(true);
        $container->register(Outstanding::class, Outstanding::class)
            ->setArguments([new Reference(Policies::class), new Reference(VersionCatalog::class), new Reference(AcceptanceRepository::class), new Reference(MemberSource::class)])
            ->setPublic(true);

        self::pages($container, $config, $clock, $locales);
        self::integrations($container, $clock, $config['pages']['layout']);
        self::reminders($container, $config['reminders'], $notifier, $clock);
    }

    /**
     * @param list<string> $locales
     *
     * @return non-empty-list<string>
     */
    private static function locales(string $default, array $locales): array
    {
        try {
            return array_values(array_unique(array_map(Identifiers::locale(...), [] === $locales ? [$default] : [$default, ...$locales])));
        } catch (InvalidTerms $invalidTerms) {
            throw new InvalidConfigurationException('phil_terms: '.$invalidTerms->getMessage(), $invalidTerms->getCode(), previous: $invalidTerms);
        }
    }

    /**
     * @param array<array-key, array{required: bool, renew_after_days: int|null, contribution_licence: bool}> $policies
     *
     * @return list<Definition>
     */
    private static function policies(array $policies): array
    {
        $definitions = [];
        $licensing = [];

        foreach ($policies as $key => $policy) {
            $key = (string) $key;

            try {
                new Policy($key, $policy['required'], $policy['renew_after_days'], $policy['contribution_licence']);
            } catch (InvalidTerms $invalidTerms) {
                throw new InvalidConfigurationException(\sprintf('phil_terms.policies.%s: %s', $key, $invalidTerms->getMessage()), $invalidTerms->getCode(), previous: $invalidTerms);
            }

            if ($policy['contribution_licence']) {
                $licensing[] = $key;
            }

            $definitions[] = new Definition(Policy::class, [$key, $policy['required'], $policy['renew_after_days'], $policy['contribution_licence']]);
        }

        if (\count($licensing) > 1) {
            throw new InvalidConfigurationException(\sprintf('phil_terms.policies: one policy at most names the licence of the contributions, not "%s".', implode('", "', $licensing)));
        }

        return $definitions;
    }

    /**
     * @param Config $config
     */
    private static function members(ContainerBuilder $container, array $config): void
    {
        if (null === $config['member_resolver']) {
            if (!$config['stable_identifiers']) {
                throw new InvalidConfigurationException('phil_terms.member_resolver is not set: the default resolver names a member by the user identifier of the security token, which is often an email address. Name a resolver that gives a stable identifier, or set phil_terms.stable_identifiers to true if the user identifiers are never email addresses.');
            }

            $container->register(TokenMemberResolver::class, TokenMemberResolver::class)->setArguments([new Reference('security.token_storage')]);
        }

        $container->setAlias(MemberResolver::class, $config['member_resolver'] ?? TokenMemberResolver::class);

        $container->setAlias(MemberSource::class, $config['member_source']);
    }

    /**
     * @param Config $config
     */
    private static function notifier(ContainerBuilder $container, array $config): ?Reference
    {
        if (null !== $config['notifier']) {
            $container->setAlias(TermsNotifier::class, $config['notifier']);

            return new Reference(TermsNotifier::class);
        }

        if (!$config['notifications']['enabled']) {
            return null;
        }

        if (!class_exists(Notifications::class)) {
            throw new InvalidConfigurationException('phil_terms.notifications needs phil/notifications-bundle: composer require phil/notifications-bundle.');
        }

        $container->register(NotificationsNotifier::class, NotificationsNotifier::class)
            ->setArguments([new Reference(Notifications::class), $config['notifications']['published_type'], $config['notifications']['reminder_type']]);
        $container->setAlias(TermsNotifier::class, NotificationsNotifier::class);
        $container->register(OutstandingAudience::class, OutstandingAudience::class)
            ->setArguments([new Reference(Policies::class), new Reference(Outstanding::class)])
            ->addTag(self::AUDIENCE_TAG);

        return new Reference(TermsNotifier::class);
    }

    /**
     * @param array{enabled: bool, ipv4_prefix: int, ipv6_prefix: int} $network
     */
    private static function masker(ContainerBuilder $container, array $network): ?Reference
    {
        if (!$network['enabled']) {
            return null;
        }

        if (!class_exists(Truncation::class)) {
            throw new InvalidConfigurationException('phil_terms.network needs phil/ip-anonymizer: composer require phil/ip-anonymizer.');
        }

        $container->register(NetworkMasker::class, NetworkMasker::class)->setArguments([new Definition(Truncation::class, [$network['ipv4_prefix'], $network['ipv6_prefix']])]);
        $container->setAlias(AddressMasker::class, NetworkMasker::class);

        return new Reference(AddressMasker::class);
    }

    /**
     * @param Config                 $config
     * @param non-empty-list<string> $locales
     */
    private static function pages(ContainerBuilder $container, array $config, Reference $clock, array $locales): void
    {
        // The subdirectory of the Toolkit is read by KitSubdirectoryPass when the configuration names none.
        $container->register(KitComponents::class, KitComponents::class)->setArguments([$config['kit_subdirectory']]);
        $container->register(TermsTwig::class, TermsTwig::class)
            ->setArguments([new Reference(KitComponents::class)])
            ->addTag('twig.attribute_extension')
            ->addTag('twig.runtime');
        $container->register(Pages::class, Pages::class)->setArguments([
            new Reference('twig'),
            new Reference('security.csrf.token_manager'),
            new Reference('router'),
            $config['pages']['layout'],
        ]);
        $container->register(AcceptanceController::class, AcceptanceController::class)
            ->setArguments([new Reference(Pages::class), new Reference(AcceptanceChecker::class), new Reference(Terms::class), new Reference(MemberResolver::class), new Reference(PolicyTexts::class), $clock, $config['default_locale']])
            ->setPublic(true)
            ->addTag('controller.service_arguments');
        $container->register(PolicyController::class, PolicyController::class)
            ->setArguments([new Reference(Pages::class), new Reference(Policies::class), new Reference(VersionCatalog::class), new Reference(PolicyTexts::class), $clock, $config['default_locale']])
            ->setPublic(true)
            ->addTag('controller.service_arguments');

        $gate = $config['gate'];

        if ($gate['enabled']) {
            // After the firewall's listener, which reads the token the member is resolved from.
            $container->register(AcceptanceGate::class, AcceptanceGate::class)
                ->setArguments([
                    new Reference(AcceptanceChecker::class),
                    new Reference(MemberResolver::class),
                    $clock,
                    new Reference('router'),
                    $gate['exempt_routes'],
                    $gate['exempt_paths'],
                    $gate['api_paths'],
                    $gate['problem_type'],
                    new Reference('translator', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                ])
                ->addTag('kernel.event_listener', ['event' => 'kernel.request', 'method' => 'onKernelRequest', 'priority' => 7]);
        }

        $admin = $config['admin'];

        if ($admin['enabled']) {
            $container->register(TermsAdminController::class, TermsAdminController::class)
                ->setArguments([
                    new Reference(Pages::class),
                    new Reference(Policies::class),
                    new Reference(VersionCatalog::class),
                    new Reference(Terms::class),
                    new Reference('security.authorization_checker'),
                    $admin['role'],
                    $admin['layout'],
                    $locales,
                    $config['timezone'],
                    class_exists(Ujo::class),
                ])
                ->setPublic(true)
                ->addTag('controller.service_arguments');
        }
    }

    private static function integrations(ContainerBuilder $container, Reference $clock, string $layout): void
    {
        if (class_exists(AbstractTypeExtension::class)) {
            $container->register(TermsAcceptanceTypeExtension::class, TermsAcceptanceTypeExtension::class)
                ->setArguments([new Reference(Policies::class), new Reference(VersionCatalog::class), $clock, new Reference('translator', ContainerInterface::NULL_ON_INVALID_REFERENCE)])
                ->addTag('form.type_extension');
            $container->register(SignupAcceptances::class, SignupAcceptances::class)->setArguments([new Reference(Terms::class)])->setPublic(true);
        }

        if (class_exists(Command::class)) {
            $container->register(OutstandingCommand::class, OutstandingCommand::class)
                ->setArguments([new Reference(Outstanding::class), $clock])
                ->addTag('console.command');

            self::legalLinks($container, $layout);
        }

        if (interface_exists(PersonalDataEraser::class)) {
            $container->register(AcceptancesPrivacy::class, AcceptancesPrivacy::class)
                ->setArguments([new Reference(AcceptanceRepository::class)])
                ->addTag(self::EXPORTER_TAG)
                ->addTag(self::ERASER_TAG);
        }
    }

    /**
     * The check of the legal links, when the legal notices bundle is enabled.
     */
    private static function legalLinks(ContainerBuilder $container, string $layout): void
    {
        // Through the bag rather than `getParameter()`, which static analysis types from the container of whatever
        // application analyses the bundle, not from the kernel that loads it.
        $bundles = $container->getParameterBag()->all()['kernel.bundles'] ?? null;

        if (!\is_array($bundles) || !isset($bundles[self::LEGAL_NOTICES_BUNDLE])) {
            return;
        }

        $container->register(LegalLinksCommand::class, LegalLinksCommand::class)
            ->setArguments([new Reference(FooterLinks::class), new Reference(Policies::class), new Reference('twig'), new Reference('request_stack'), $layout])
            ->addTag('console.command');
    }

    /**
     * @param array{enabled: bool, frequency: string, days_ahead: int} $reminders
     */
    private static function reminders(ContainerBuilder $container, array $reminders, ?Reference $notifier, Reference $clock): void
    {
        if (!$reminders['enabled']) {
            return;
        }

        if (!$notifier instanceof Reference) {
            throw new InvalidConfigurationException('phil_terms.reminders needs a notifier: enable phil_terms.notifications or name phil_terms.notifier.');
        }

        if (!interface_exists(ScheduleProviderInterface::class)) {
            throw new InvalidConfigurationException('phil_terms.reminders needs symfony/scheduler: composer require symfony/scheduler.');
        }

        $container->register(SendTermsRemindersHandler::class, SendTermsRemindersHandler::class)
            ->setArguments([new Reference(Policies::class), new Reference(VersionCatalog::class), new Reference(Outstanding::class), $notifier, $clock, $reminders['days_ahead']])
            ->addTag('messenger.message_handler', ['handles' => SendTermsReminders::class]);
        $container->register(TermsSchedule::class, TermsSchedule::class)
            ->setArguments([$reminders['frequency']])
            ->addTag('scheduler.schedule_provider', ['name' => TermsSchedule::NAME]);
    }
}

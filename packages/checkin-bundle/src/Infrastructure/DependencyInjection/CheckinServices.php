<?php

declare(strict_types=1);

namespace Phil\CheckinBundle\Infrastructure\DependencyInjection;

use DateTimeZone;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Phil\Checkin\AdmissionPolicy;
use Phil\Checkin\Contract\CheckInLedger;
use Phil\Checkin\Contract\CredentialCatalog;
use Phil\Checkin\Contract\CredentialResolver;
use Phil\Checkin\CredentialResolvers;
use Phil\Checkin\InMemoryCheckInLedger;
use Phil\Checkin\Offline\OfflineSync;
use Phil\CheckinBundle\Application\Contract\AdmissionRules;
use Phil\CheckinBundle\Application\Contract\CredentialAttributes;
use Phil\CheckinBundle\Infrastructure\Catalog\CredentialCatalogs;
use Phil\CheckinBundle\Infrastructure\Doctrine\CheckInSchemaListener;
use Phil\CheckinBundle\Infrastructure\Doctrine\DoctrineCheckInLedger;
use Phil\CheckinBundle\Infrastructure\Http\CheckinController;
use Phil\CheckinBundle\Infrastructure\Http\CheckinRequest;
use Phil\CheckinBundle\Infrastructure\Http\DeskConfig;
use Phil\CheckinBundle\Infrastructure\Rules\CheckpointDesks;
use Phil\CheckinBundle\Infrastructure\Rules\CheckpointRules;
use Phil\CheckinBundle\Infrastructure\Rules\ConfiguredAdmissionRules;
use Phil\CheckinBundle\Infrastructure\Rules\NoCredentialAttributes;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;

/**
 * The services of the configuration: the ledger, the resolvers asked together, the policy, the admission rules, the
 * desks of the checkpoints, the synchronisation over the catalog the application names or the catalogs it tags, and
 * the endpoints with their configuration.
 *
 * @phpstan-type Config array{enabled: bool, clock: string, store: string, connection: string, catalog: ?string, policy: array{early_seconds: int, anti_passback: bool}, rules: array{checkpoints: array<string, string>, provider: ?string, attributes: ?string, timezone: ?string}, offline: array{manifest_seconds: int, max_credentials: int, labels: bool, min_code_bits: int, batch_limit: int, clock_skew_seconds: int, max_age_seconds: int}}
 */
final readonly class CheckinServices
{
    /**
     * The tag of the services implementing `CredentialResolver`, asked in the order of their priority.
     */
    public const string RESOLVER_TAG = 'phil_checkin.credential_resolver';

    /**
     * The tag of the services implementing `CredentialCatalog`, asked in the order of their priority when the
     * configuration names no catalog.
     */
    public const string CATALOG_TAG = 'phil_checkin.credential_catalog';

    public const string STORE_DOCTRINE = 'doctrine';

    public const string STORE_MEMORY = 'memory';

    /**
     * @param Config $config
     */
    public static function register(ContainerBuilder $container, array $config): void
    {
        $container->registerForAutoconfiguration(CredentialResolver::class)->addTag(self::RESOLVER_TAG);
        $container->registerForAutoconfiguration(CredentialCatalog::class)->addTag(self::CATALOG_TAG);

        $ledger = new Reference(self::ledger($container, $config));
        $clock = new Reference($config['clock']);
        $policy = new Reference(AdmissionPolicy::class);

        $container->setAlias(CheckInLedger::class, (string) $ledger);
        $container->register(CredentialResolvers::class, CredentialResolvers::class)
            ->setArguments([new TaggedIteratorArgument(self::RESOLVER_TAG)]);
        $container->register(AdmissionPolicy::class, AdmissionPolicy::class)
            ->setArguments([$config['policy']['early_seconds'], $config['policy']['anti_passback']]);
        self::rules($container, $config['rules'], $clock);

        // The catalog named, or the tagged ones asked together; CredentialCatalogPass turns offline off without any.
        if (null === $config['catalog']) {
            $container->register(CredentialCatalogs::class, CredentialCatalogs::class)
                ->setArguments([new TaggedIteratorArgument(self::CATALOG_TAG)]);
        }

        $catalog = new Reference($config['catalog'] ?? CredentialCatalogs::class);

        $container->register(CheckpointDesks::class, CheckpointDesks::class)
            ->setArguments([new Reference(CredentialResolvers::class), $ledger, $clock, $policy, new Reference(CheckpointRules::class), $catalog, $config['offline']['manifest_seconds'], $config['offline']['max_credentials'], $config['offline']['labels'], $config['offline']['min_code_bits']]);

        $container->register(OfflineSync::class, OfflineSync::class)
            ->setArguments([$catalog, $ledger, $clock, $policy, $config['offline']['clock_skew_seconds'], $config['offline']['max_age_seconds']]);

        $container->register(CheckinRequest::class, CheckinRequest::class)
            ->setArguments([new Reference(CsrfProtectionPass::TOKEN_MANAGER)]);
        $container->register(DeskConfig::class, DeskConfig::class)
            ->setArguments([new Reference(CheckinRequest::class), new Reference('router', ContainerInterface::NULL_ON_INVALID_REFERENCE), true]);
        $container->register(CheckinController::class, CheckinController::class)
            ->setArguments([
                new Reference(CheckpointDesks::class),
                new Reference(CheckinRequest::class),
                new Reference('security.authorization_checker', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                new Reference(OfflineSync::class),
                $config['offline']['batch_limit'],
                $config['enabled'],
                new Reference('event_dispatcher', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])
            ->setPublic(true)
            ->addTag('controller.service_arguments');
    }

    /**
     * The admission rules — the configuration's, each checked when the container compiles, or the application's
     * service —, the attributes the rules read, and their evaluator.
     *
     * @param array{checkpoints: array<string, string>, provider: ?string, attributes: ?string, timezone: ?string} $rules
     *
     * @throws InvalidArgumentException when a rule of the configuration does not parse
     */
    private static function rules(ContainerBuilder $container, array $rules, Reference $clock): void
    {
        foreach ($rules['checkpoints'] as $checkpoint => $rule) {
            $error = CheckpointRules::lint($rule);

            if (null !== $error) {
                throw new InvalidArgumentException(\sprintf('The admission rule of the checkpoint "%s" is not valid: %s', $checkpoint, $error));
            }
        }

        $container->register(ConfiguredAdmissionRules::class, ConfiguredAdmissionRules::class)->setArguments([$rules['checkpoints']]);
        $container->setAlias(AdmissionRules::class, $rules['provider'] ?? ConfiguredAdmissionRules::class);

        if (null === $rules['attributes']) {
            $container->register(NoCredentialAttributes::class, NoCredentialAttributes::class);
        }

        $container->setAlias(CredentialAttributes::class, $rules['attributes'] ?? NoCredentialAttributes::class);
        $container->register(CheckpointRules::class, CheckpointRules::class)->setArguments([
            new Reference(AdmissionRules::class),
            $clock,
            new Reference(CredentialAttributes::class),
            null === $rules['timezone'] ? null : new Definition(DateTimeZone::class, [$rules['timezone']]),
        ]);
    }

    /**
     * The ledger's service id: the Doctrine ledger and its schema listener, the in-memory ledger, or the application's.
     *
     * @param Config $config
     */
    private static function ledger(ContainerBuilder $container, array $config): string
    {
        if (self::STORE_MEMORY === $config['store']) {
            $container->register(InMemoryCheckInLedger::class, InMemoryCheckInLedger::class);

            return InMemoryCheckInLedger::class;
        }

        if (self::STORE_DOCTRINE !== $config['store']) {
            return $config['store'];
        }

        $connection = new Reference(\sprintf('doctrine.dbal.%s_connection', $config['connection']));
        $container->register(DoctrineCheckInLedger::class, DoctrineCheckInLedger::class)->setArguments([$connection]);

        if (class_exists(GenerateSchemaEventArgs::class)) {
            $container->register(CheckInSchemaListener::class, CheckInSchemaListener::class)
                ->setArguments([$connection, new Reference(DoctrineCheckInLedger::class)])
                ->addTag('doctrine.event_listener', ['event' => 'postGenerateSchema', 'connection' => $config['connection']]);
        }

        return DoctrineCheckInLedger::class;
    }
}

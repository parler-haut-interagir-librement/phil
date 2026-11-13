<?php

declare(strict_types=1);

namespace Phil\CheckinBundle;

use DateTimeZone;
use Override;
use Phil\Checkin\AdmissionPolicy;
use Phil\Checkin\Offline\ManifestIssuer;
use Phil\Checkin\Offline\OfflineBatch;
use Phil\CheckinBundle\Infrastructure\DependencyInjection\CheckinServices;
use Phil\CheckinBundle\Infrastructure\DependencyInjection\CredentialCatalogPass;
use Phil\CheckinBundle\Infrastructure\DependencyInjection\CsrfProtectionPass;
use Phil\CheckinBundle\Infrastructure\Http\CheckinController;
use Phil\CheckinBundle\Infrastructure\Http\CheckinRequest;
use Phil\CheckinBundle\Infrastructure\Rules\CheckpointRules;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * `phil/checkin` in a Symfony application, from its configuration:
 *
 * ```yaml
 * phil_checkin:
 *     enabled: true
 *     catalog: App\Checkin\TicketCatalog      # offline devices; null asks the tagged catalogs
 *     policy:
 *         early_seconds: 1800
 *         anti_passback: true
 *     rules:
 *         timezone: America/Toronto
 *         checkpoints:
 *             bar: "attributes['age'] >= 18"
 *             workshop: "now.format('H') >= '09' and credential.label != null"
 * ```
 *
 * The bundle wires the desk on the resolvers tagged {@see self::RESOLVER_TAG} asked together, a ledger kept by
 * Doctrine, in memory or by the application, the offline manifests and synchronisation over the application's
 * catalog or the catalogs tagged {@see self::CATALOG_TAG}, the admission rule of each checkpoint in Symfony's
 * ExpressionLanguage, and the endpoints of the desk controller, granted by the application's security on the
 * attribute {@see self::SCAN}. The controller's CSRF token joins the stateless ones of FrameworkBundle, and with AssetMapper the
 * controller and its stylesheet are served without a build step.
 */
final class PhilCheckinBundle extends AbstractBundle
{
    /**
     * The tag of the services implementing `CredentialResolver`, asked in the order of their priority; a service of
     * the application implementing the interface is tagged by autoconfiguration.
     */
    public const string RESOLVER_TAG = CheckinServices::RESOLVER_TAG;

    /**
     * The tag of the services implementing `CredentialCatalog`, asked together in the order of their priority when
     * `catalog` is null; a service of the application implementing the interface is tagged by autoconfiguration.
     */
    public const string CATALOG_TAG = CheckinServices::CATALOG_TAG;

    /**
     * The attribute the application's security grants on a `CheckpointId` to whoever may check in there: a voter of
     * the application decides, and without one, nobody may.
     */
    public const string SCAN = CheckinController::SCAN;

    public const string CONTROLLERS_DIR = __DIR__.'/../assets/src';

    public const string STYLES_DIR = __DIR__.'/../assets/styles';

    #[Override]
    protected string $extensionAlias = 'phil_checkin';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->booleanNode('enabled')
                    ->info('Whether the endpoints answer; off, they answer 404.')
                    ->defaultFalse()
                ->end()
                ->scalarNode('clock')
                    ->info('Service id of the PSR-20 clock scans are timed on.')
                    ->defaultValue('clock')
                    ->cannotBeEmpty()
                ->end()
                ->scalarNode('store')
                    ->info('Where check-ins are kept: "doctrine", "memory" for tests, or the service id of a CheckInLedger.')
                    ->defaultValue(CheckinServices::STORE_DOCTRINE)
                    ->cannotBeEmpty()
                ->end()
                ->scalarNode('connection')
                    ->info('The Doctrine connection of the check-in table.')
                    ->defaultValue('default')
                    ->cannotBeEmpty()
                ->end()
                ->scalarNode('catalog')
                    ->info('Service id of the CredentialCatalog that lists credentials for offline devices; null asks the services tagged phil_checkin.credential_catalog together, and checks in online only without any.')
                    ->defaultNull()
                ->end()
                ->arrayNode('policy')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('early_seconds')
                            ->info('How many seconds before its opening a credential is let in.')
                            ->min(0)
                            ->max(AdmissionPolicy::MAX_EARLY_SECONDS)
                            ->defaultValue(0)
                        ->end()
                        ->booleanNode('anti_passback')
                            ->info('Whether a holder inside must check out before coming in again.')
                            ->defaultTrue()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('rules')
                    ->info('The admission rule of each checkpoint, an ExpressionLanguage expression a credential meets to enter, on top of the policy.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('checkpoints')
                            ->info('The rule of each checkpoint, by its name; it reads credential, attributes, checkpoint and now.')
                            ->useAttributeAsKey('checkpoint')
                            ->normalizeKeys(false)
                            ->scalarPrototype()
                                ->cannotBeEmpty()
                                ->validate()
                                    ->ifTrue(static fn (mixed $rule): bool => !\is_string($rule) || \strlen($rule) > CheckpointRules::MAX_LENGTH)
                                    ->thenInvalid(\sprintf('A rule is a text of at most %d bytes.', CheckpointRules::MAX_LENGTH))
                                ->end()
                            ->end()
                        ->end()
                        ->scalarNode('provider')
                            ->info('Service id of the AdmissionRules that gives the rules instead of this configuration, such as rules kept in the database.')
                            ->defaultNull()
                        ->end()
                        ->scalarNode('attributes')
                            ->info('Service id of the CredentialAttributes whose answer the rules read as attributes; null gives none.')
                            ->defaultNull()
                        ->end()
                        ->scalarNode('timezone')
                            ->info("The time zone of now in the rules; null keeps the clock's.")
                            ->defaultNull()
                            ->validate()
                                ->ifTrue(static fn (mixed $zone): bool => !\in_array($zone, DateTimeZone::listIdentifiers(), true))
                                ->thenInvalid('%s is not a time zone identifier.')
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('offline')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('manifest_seconds')
                            ->info('How long a device may use an offline manifest.')
                            ->min(60)
                            ->max(ManifestIssuer::MAX_SECONDS)
                            ->defaultValue(43_200)
                        ->end()
                        ->integerNode('max_credentials')
                            ->info('How many credentials a manifest carries at most.')
                            ->min(1)
                            ->max(ManifestIssuer::MAX_LIMIT)
                            ->defaultValue(10_000)
                        ->end()
                        ->booleanNode('labels')
                            ->info("Whether manifests carry the labels of the credentials, such as the holders' names.")
                            ->defaultFalse()
                        ->end()
                        ->integerNode('min_code_bits')
                            ->info('How many bits a code needs to be carried by a manifest, which carries its key; 0 takes any code.')
                            ->min(0)
                            ->max(ManifestIssuer::MAX_CODE_BITS)
                            ->defaultValue(64)
                        ->end()
                        ->integerNode('batch_limit')
                            ->info('How many scans a device sends at most in one synchronisation.')
                            ->min(1)
                            ->max(OfflineBatch::LIMIT)
                            ->defaultValue(OfflineBatch::LIMIT)
                        ->end()
                        ->integerNode('clock_skew_seconds')
                            ->info('How far ahead of the server a device clock may be.')
                            ->min(0)
                            ->max(3_600)
                            ->defaultValue(300)
                        ->end()
                        ->integerNode('max_age_seconds')
                            ->info('How old a scan made offline may be when it is synchronised.')
                            ->min(60)
                            ->max(2_592_000)
                            ->defaultValue(172_800)
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    #[Override]
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if ($builder->hasExtension('framework')) {
            $container->extension('framework', ['csrf_protection' => ['stateless_token_ids' => [CheckinRequest::TOKEN_ID]]]);
        }

        // The controller under the name Symfony UX gives the controllers of a package, and its stylesheet, for
        // AssetMapper to serve.
        if ($builder->hasExtension('asset_mapper')) {
            $container->extension('asset_mapper', ['paths' => [
                self::CONTROLLERS_DIR => '@phil/checkin-bundle',
                self::STYLES_DIR => 'phil-checkin',
            ]]);
        }
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var array{enabled: bool, clock: string, store: string, connection: string, catalog: ?string, policy: array{early_seconds: int, anti_passback: bool}, rules: array{checkpoints: array<string, string>, provider: ?string, attributes: ?string, timezone: ?string}, offline: array{manifest_seconds: int, max_credentials: int, labels: bool, min_code_bits: int, batch_limit: int, clock_skew_seconds: int, max_age_seconds: int}} $config */
        CheckinServices::register($builder, $config);
    }

    /**
     * Stops the container with a helpful message when the application turns the CSRF protection off, and turns the
     * offline services off when there is no catalog.
     */
    #[Override]
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new CsrfProtectionPass());
        $container->addCompilerPass(new CredentialCatalogPass());
    }
}

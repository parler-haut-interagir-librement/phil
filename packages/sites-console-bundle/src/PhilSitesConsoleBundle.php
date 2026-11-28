<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle;

use Override;
use Phil\SitesConsoleBundle\Domain\Model\Site;
use Phil\SitesConsoleBundle\Domain\Version\Packages;
use Phil\SitesConsoleBundle\Infrastructure\DependencyInjection\KitSubdirectoryPass;
use Phil\SitesConsoleBundle\Infrastructure\DependencyInjection\MonitoringPass;
use Phil\SitesConsoleBundle\Infrastructure\DependencyInjection\SitesConsoleServices;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * A console for operating several Symfony sites, and the agent each site runs for it:
 *
 * ```yaml
 * # On the console
 * phil_sites_console:
 *     console:
 *         enabled: true
 *         private_key: '%env(SITES_CONSOLE_PRIVATE_KEY)%'
 *         # next_private_key: '%env(SITES_CONSOLE_NEXT_PRIVATE_KEY)%' during a rotation
 *
 * # On each site
 * phil_sites_console:
 *     agent:
 *         enabled: true
 *         site_id: shop
 *         private_key: '%env(SITES_CONSOLE_AGENT_KEY)%'
 *         console_public_key: '%env(SITES_CONSOLE_PUBLIC_KEY)%'
 * ```
 *
 * The console pulls each site's versions and health through a signed request, keeps snapshots, compares versions and
 * raises alerts, pushes common settings once per push, and runs update plans in batches that stop at the first
 * failure. Every exchange is signed both ways with Ed25519 keys; the console keeps no credential of a site, and its
 * own key rotates without cutting any site off: each agent accepts the current and the next key during a rotation.
 *
 * @phpstan-import-type Config from SitesConsoleServices
 */
final class PhilSitesConsoleBundle extends AbstractBundle
{
    #[Override]
    protected string $extensionAlias = 'phil_sites_console';

    #[Override]
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new KitSubdirectoryPass());
        $container->addCompilerPass(new MonitoringPass());
    }

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('clock')->info('Service id of the PSR-20 clock.')->defaultValue('clock')->cannotBeEmpty()->end()
                ->append($this->console())
                ->append($this->agent())
            ->end();
    }

    #[Override]
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if (!$builder->hasExtension('doctrine') || !$this->consoleOnDoctrine($builder->getExtensionConfig($this->extensionAlias))) {
            return;
        }

        $container->extension('doctrine', ['orm' => ['mappings' => ['PhilSitesConsole' => [
            'type' => 'attribute',
            'is_bundle' => false,
            'dir' => __DIR__.'/Domain/Model',
            'prefix' => 'Phil\SitesConsoleBundle\Domain\Model',
        ]]]]);
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var Config $config */
        SitesConsoleServices::register($builder, $config);
    }

    /**
     * Whether a raw configuration turns the console on with its Doctrine store, the default.
     *
     * @param list<mixed> $configs
     */
    private function consoleOnDoctrine(array $configs): bool
    {
        $enabled = false;
        $store = SitesConsoleServices::STORE_DOCTRINE;

        foreach ($configs as $config) {
            $console = \is_array($config) ? ($config['console'] ?? null) : null;

            if (\is_array($console)) {
                $enabled = !\array_key_exists('enabled', $console) || true === $console['enabled'];
                $store = \is_string($console['store'] ?? null) ? $console['store'] : $store;
            }
        }

        return $enabled && SitesConsoleServices::STORE_DOCTRINE === $store;
    }

    private function console(): ArrayNodeDefinition
    {
        $node = new ArrayNodeDefinition('console');
        $node
            ->info('The console: the registry of sites, the snapshots, the pushes, the update plans and the screens.')
            ->canBeEnabled()
            ->children()
                ->scalarNode('private_key')->info('The base64 of the Ed25519 key the console signs its requests with, from phil:sites-console:keys; an environment variable or a secret.')->defaultNull()->end()
                ->scalarNode('next_private_key')->info('The base64 of the key phil:sites-console:keys:rotate moves the sites to; the console signs with it once every site holds it. An environment variable or a secret.')->defaultNull()->end()
                ->enumNode('store')->info('Where the console keeps its rows: "doctrine", or "memory" for tests.')->values([SitesConsoleServices::STORE_DOCTRINE, SitesConsoleServices::STORE_MEMORY])->defaultValue(SitesConsoleServices::STORE_DOCTRINE)->end()
                ->scalarNode('entity_manager')->info('The Doctrine entity manager of the tables.')->defaultValue('default')->cannotBeEmpty()->end()
                ->scalarNode('http_client')->info('Service id of the HTTP client the agents are reached with.')->defaultValue('http_client')->cannotBeEmpty()->end()
                ->booleanNode('allow_insecure_urls')->info('Whether an agent may answer on http or localhost; for development and tests only.')->defaultFalse()->end()
                ->floatNode('timeout')->info('The seconds a status or a push may take.')->min(1)->max(120)->defaultValue(10.0)->end()
                ->floatNode('update_timeout')->info('The seconds the update of one site may take.')->min(1)->max(3600)->defaultValue(600.0)->end()
                ->arrayNode('watched_packages')
                    ->info('The packages compared across sites, by pattern where * stands for any characters.')
                    ->scalarPrototype()->cannotBeEmpty()->end()
                    ->defaultValue(['phil/*', 'symfony/http-kernel'])
                ->end()
                ->arrayNode('expected_versions')
                    ->info('The lowest version each package should run; a site below it raises an alert.')
                    ->useAttributeAsKey('package')
                    ->normalizeKeys(false)
                    ->scalarPrototype()
                        ->validate()->ifTrue(static fn (mixed $version): bool => !\is_string($version) || !Packages::isVersion($version))->thenInvalid('An expected version is a version, such as 1.4.2.')->end()
                    ->end()
                    ->validate()
                        ->ifTrue(static fn (array $versions): bool => [] !== array_filter(array_keys($versions), static fn (int|string $package): bool => !Packages::isName((string) $package)))
                        ->thenInvalid('The expected versions are keyed by package name, such as vendor/package.')
                    ->end()
                ->end()
                ->integerNode('snapshot_retention_days')->info('The days of snapshots phil:sites-console:purge keeps; the latest of each site always stays.')->min(1)->defaultValue(90)->end()
                ->arrayNode('roles')
                    ->info('The roles of the user token that open the console, filled by the firewall from the identity provider.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('viewer')->info('Reads the screens.')->defaultValue('ROLE_SITES_CONSOLE_VIEWER')->cannotBeEmpty()->end()
                        ->scalarNode('operator')->info('Pulls, delivers pushes and runs update plans.')->defaultValue('ROLE_SITES_CONSOLE_OPERATOR')->cannotBeEmpty()->end()
                    ->end()
                ->end()
                ->scalarNode('layout')->info('The Twig layout the screens extend; it defines the "title" and "content" blocks.')->defaultValue('@PhilSitesConsole/layout.html.twig')->cannotBeEmpty()->end()
                ->scalarNode('kit_subdirectory')->info('Where the recipes of the kit are, under the components directory; the subdirectory of "ux_toolkit" when null.')->defaultNull()->end()
            ->end()
            ->validate()
                ->ifTrue(static fn (array $console): bool => true === $console['enabled'] && null === $console['private_key'])
                ->thenInvalid('The console signs its requests: set console.private_key.')
            ->end();

        return $node;
    }

    private function agent(): ArrayNodeDefinition
    {
        $node = new ArrayNodeDefinition('agent');
        $node
            ->info('The agent of this site: the signed endpoints the console reads and acts through.')
            ->canBeEnabled()
            ->children()
                ->scalarNode('site_id')
                    ->info('The identifier of this site in the console; requests meant for another site are refused.')
                    ->defaultNull()
                    ->validate()->ifTrue(static fn (mixed $id): bool => !\is_string($id) || 1 !== preg_match(Site::ID_PATTERN, $id))->thenInvalid('The identifier of a site is 1 to 64 lowercase letters, digits and inner dashes, starting with a letter.')->end()
                ->end()
                ->scalarNode('private_key')->info('The base64 of the Ed25519 key this site signs its responses with; an environment variable or a secret.')->defaultNull()->end()
                ->scalarNode('console_public_key')->info('The base64 of the public key of the console, which verifies its requests.')->defaultNull()->end()
                ->scalarNode('console_next_public_key')->info('The base64 of a second public key of the console accepted during a rotation, when it is given by deploy rather than by the rotation.')->defaultNull()->end()
                ->scalarNode('lock_file')->info('The composer.lock the versions are read from.')->defaultValue('%kernel.project_dir%/composer.lock')->cannotBeEmpty()->end()
                ->booleanNode('include_dev')->info('Whether the development packages are reported too.')->defaultFalse()->end()
                ->arrayNode('packages')->info('The packages reported, by pattern; every package when empty.')->scalarPrototype()->cannotBeEmpty()->end()->end()
                ->arrayNode('configuration_keys')->info('The setting keys a push may carry, by pattern such as "mail.*"; no push is accepted when empty.')->scalarPrototype()->cannotBeEmpty()->end()->end()
                ->scalarNode('configuration_applier')->info('Service id of the ConfigurationApplier that writes pushed settings; pushes answer 501 without one.')->defaultNull()->end()
                ->scalarNode('update_runner')->info('Service id of the UpdateRunner that starts an update; updates answer 501 without one.')->defaultNull()->end()
                ->enumNode('health')->info('Where the health comes from: "monitoring", the checks of phil/monitoring-bundle; "none", unknown; "auto", the first when it is registered.')->values(['auto', 'monitoring', 'none'])->defaultValue('auto')->end()
                ->scalarNode('cache_pool')->info('Service id of the cache pool shared by the servers of the site, for the nonces and the idempotency keys.')->defaultValue('cache.app')->cannotBeEmpty()->end()
                ->scalarNode('key_pool')->info('Service id of the cache pool that keeps the console keys a rotation stages and retires; shared by the servers, surviving deploys. The cache pool when null.')->defaultNull()->end()
                ->scalarNode('lock_factory')->info('Service id of the lock factory, shared by the servers of the site, that makes the nonces and the idempotency atomic.')->defaultValue('lock.factory')->cannotBeEmpty()->end()
                ->integerNode('max_clock_skew')->info('The seconds a request date may differ from the clock of the site.')->min(1)->max(3600)->defaultValue(300)->end()
            ->end()
            ->validate()
                ->ifTrue(static fn (array $agent): bool => true === $agent['enabled'] && (null === $agent['site_id'] || null === $agent['private_key'] || null === $agent['console_public_key']))
                ->thenInvalid('The agent names its site and holds its private key and the public key of the console: set agent.site_id, agent.private_key and agent.console_public_key.')
            ->end();

        return $node;
    }
}

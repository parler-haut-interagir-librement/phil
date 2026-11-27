<?php

declare(strict_types=1);

namespace Phil\TenancyBundle;

use Override;
use Phil\TenancyBundle\Application\ActiveTenant;
use Phil\TenancyBundle\Application\Contract\AccountSharing;
use Phil\TenancyBundle\Application\Contract\ActiveTenantStore;
use Phil\TenancyBundle\Application\Contract\HostAliases;
use Phil\TenancyBundle\Application\Contract\HostTenants;
use Phil\TenancyBundle\Application\Contract\ReachableTenantsProvider;
use Phil\TenancyBundle\Application\Contract\TenantActivation;
use Phil\TenancyBundle\Application\Shop\ShopContext;
use Phil\TenancyBundle\Application\TenantContext;
use Phil\TenancyBundle\Domain\Shop\ShopNetwork;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use Phil\TenancyBundle\Infrastructure\Cache\TenantCachePass;
use Phil\TenancyBundle\Infrastructure\Console\RowLevelSecurityCheckCommand;
use Phil\TenancyBundle\Infrastructure\Console\RowLevelSecurityPoliciesCommand;
use Phil\TenancyBundle\Infrastructure\Console\TenantOption;
use Phil\TenancyBundle\Infrastructure\Doctrine\ActiveTenantFilter;
use Phil\TenancyBundle\Infrastructure\Doctrine\DoctrineTenantActivation;
use Phil\TenancyBundle\Infrastructure\Doctrine\RowLevelSecurity\RowLevelSecurityConfig;
use Phil\TenancyBundle\Infrastructure\Doctrine\RowLevelSecurity\RowLevelSecurityPolicies;
use Phil\TenancyBundle\Infrastructure\Doctrine\RowLevelSecurity\TenantSession;
use Phil\TenancyBundle\Infrastructure\Doctrine\RowLevelSecurity\TenantSessionMiddleware;
use Phil\TenancyBundle\Infrastructure\Doctrine\TenantWriteGuard;
use Phil\TenancyBundle\Infrastructure\Http\ConfiguredHostTenants;
use Phil\TenancyBundle\Infrastructure\Http\CurrentTenantValueResolver;
use Phil\TenancyBundle\Infrastructure\Http\RedirectHostAlias;
use Phil\TenancyBundle\Infrastructure\Http\ResolveActiveTenant;
use Phil\TenancyBundle\Infrastructure\Http\ResolveHostTenant;
use Phil\TenancyBundle\Infrastructure\Http\SwitchActiveTenant;
use Phil\TenancyBundle\Infrastructure\Mailer\ConfiguredTenantMailSettings;
use Phil\TenancyBundle\Infrastructure\Mailer\Contract\TenantMailSettings;
use Phil\TenancyBundle\Infrastructure\Mailer\TenantTransports;
use Phil\TenancyBundle\Infrastructure\Messenger\TenantMiddleware;
use Phil\TenancyBundle\Infrastructure\Security\ConfiguredAccountSharing;
use Phil\TenancyBundle\Infrastructure\Security\RememberSignInTenant;
use Phil\TenancyBundle\Infrastructure\Security\TenantRequestMatcher;
use Phil\TenancyBundle\Infrastructure\Security\TenantScopedUserProvider;
use Phil\TenancyBundle\Infrastructure\Security\TenantUserProviders;
use Phil\TenancyBundle\Infrastructure\Session\SessionActiveTenantStore;
use Phil\TenancyBundle\Infrastructure\Twig\TenancyExtension;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Config\Definition\Configuration;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;
use Twig\Attribute\AsTwigFunction;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service_locator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

/**
 * The active tenant of a multi-tenant application, wired: the store of the remembered choice, `ActiveTenant`
 * over the application's reachable tenants, the tenant context and its activations, the request listener, the
 * switch, the `#[CurrentTenant]` argument, the tenant of each host and the aliases of the hosts, the shop network,
 * and — when their components are installed — the Messenger middleware, the `--tenant` option, the Twig functions, the
 * sessions scoped to a tenant, the firewalls and user providers of each tenant, and the mail of each tenant.
 * `cache.app` and the pools built on it are scoped to the active tenant, except the pools every tenant shares. The
 * Doctrine filter is registered enabled on the default entity manager; the row-level security of PostgreSQL, a second
 * barrier behind it, is off until enabled.
 */
final class PhilTenancyBundle extends AbstractBundle
{
    public const string ACTIVATION_TAG = 'phil_tenancy.activation';

    /**
     * The prefix of the request matcher of each `authentication.tenant_firewalls` entry.
     */
    public const string REQUEST_MATCHER_PREFIX = 'phil_tenancy.request_matcher.';

    /**
     * The prefix of the user provider of each `authentication.tenant_user_providers` entry.
     */
    public const string USER_PROVIDER_PREFIX = 'phil_tenancy.user_provider.';

    #[Override]
    protected string $extensionAlias = 'phil_tenancy';

    #[Override]
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new TenantCachePass(self::ACTIVATION_TAG));
    }

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('store')
                    ->info('Where the chosen tenant is remembered: "session", or the id of an ActiveTenantStore service.')
                    ->defaultValue('session')
                    ->cannotBeEmpty()
                ->end()
                ->scalarNode('reachable_tenants')
                    ->info('The id of the ReachableTenantsProvider service; without it, nothing that resolves a tenant is registered.')
                    ->defaultNull()
                ->end()
                ->booleanNode('doctrine_filter')
                    ->info('Registers the phil_tenancy filter, enabled, on the default entity manager.')
                    ->defaultTrue()
                ->end()
                ->arrayNode('request')
                    ->canBeDisabled()
                    ->children()
                        ->scalarNode('selector_route')
                            ->info('The route an account that must choose a tenant is sent to; null to carry on in no tenant.')
                            ->defaultNull()
                        ->end()
                        ->arrayNode('ignored_routes')
                            ->info('Routes resolved in no tenant, such as the login page.')
                            ->scalarPrototype()->end()
                        ->end()
                    ->end()
                ->end()
                ->booleanNode('console')
                    ->info('Gives every command a --tenant option.')
                    ->defaultTrue()
                ->end()
                ->arrayNode('hosts')
                    ->info('The tenant each host serves, entered before the firewall: a host alone, no scheme, port or path.')
                    ->useAttributeAsKey('host')
                    ->normalizeKeys(false)
                    ->scalarPrototype()->cannotBeEmpty()->end()
                ->end()
                ->arrayNode('host_aliases')
                    ->info('The other names of the hosts, each the canonical host it stands for: an alias serves the tenant of its host.')
                    ->useAttributeAsKey('alias')
                    ->normalizeKeys(false)
                    ->scalarPrototype()->cannotBeEmpty()->end()
                ->end()
                ->booleanNode('redirect_host_aliases')
                    ->info('Whether a request for an alias is sent to its canonical host, permanently, before any tenant is entered.')
                    ->defaultTrue()
                ->end()
                ->arrayNode('shops')
                    ->info('The shops among the tenants, and the shops whose catalogues each one includes and takes payment for at its checkout.')
                    ->useAttributeAsKey('shop')
                    ->normalizeKeys(false)
                    ->arrayPrototype()
                        ->children()
                            ->arrayNode('includes')
                                ->scalarPrototype()->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('authentication')
                    ->info('Scopes the sessions of the listed user providers to the tenant their accounts signed in on.')
                    ->canBeEnabled()
                    ->children()
                        ->arrayNode('user_providers')
                            ->info('The service ids of the user providers to scope, such as security.user.provider.concrete.app_users.')
                            ->scalarPrototype()->cannotBeEmpty()->end()
                        ->end()
                        ->scalarNode('account_sharing')
                            ->info('The id of an AccountSharing service; by default, shared_tenants and the shop network.')
                            ->defaultNull()
                        ->end()
                        ->booleanNode('shared_sessions')
                            ->info('Whether a session opened in no tenant, as on a central back office, is signed in on every tenant; off by default.')
                            ->defaultFalse()
                        ->end()
                        ->arrayNode('shared_tenants')
                            ->info('Groups of tenants whose accounts are signed in on every tenant of the group.')
                            ->arrayPrototype()
                                ->scalarPrototype()->end()
                            ->end()
                        ->end()
                        ->arrayNode('tenant_firewalls')
                            ->info('Request matchers for the firewalls of security.yaml: each name registers phil_tenancy.request_matcher.<name>, which matches the requests in its tenants.')
                            ->useAttributeAsKey('name')
                            ->normalizeKeys(false)
                            ->arrayPrototype()
                                ->scalarPrototype()->cannotBeEmpty()->end()
                            ->end()
                        ->end()
                        ->arrayNode('tenant_user_providers')
                            ->info('User providers chosen for each tenant: each name registers phil_tenancy.user_provider.<name>, the id of a provider of security.yaml.')
                            ->useAttributeAsKey('name')
                            ->normalizeKeys(false)
                            ->arrayPrototype()
                                ->children()
                                    ->arrayNode('tenants')
                                        ->info('The service id of the user provider of each tenant, by tenant reference.')
                                        ->useAttributeAsKey('tenant')
                                        ->normalizeKeys(false)
                                        ->scalarPrototype()->cannotBeEmpty()->end()
                                    ->end()
                                    ->scalarNode('default')
                                        ->info('The service id of the user provider of the other tenants and of a request in no tenant; without it, they have no account.')
                                        ->defaultNull()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('mailer')
                    ->info("How each tenant's mail leaves: its transport, its sender, or the platform's sender with a Reply-To.")
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('settings')
                            ->info('The id of a TenantMailSettings service, instead of the tenants below.')
                            ->defaultNull()
                        ->end()
                        ->scalarNode('platform_sender')
                            ->info('The sender of the tenants without a domain of their own, such as "Platform <mail@platform.example>".')
                            ->defaultNull()
                        ->end()
                        ->arrayNode('tenants')
                            ->useAttributeAsKey('tenant')
                            ->normalizeKeys(false)
                            ->arrayPrototype()
                                ->children()
                                    ->scalarNode('transport')->info('A transport named under framework.mailer.transports.')->defaultNull()->end()
                                    ->scalarNode('sender')->info("The tenant's own sender, on its own domain.")->defaultNull()->end()
                                    ->scalarNode('name')->info("The name the tenant's mail is sent under.")->defaultNull()->end()
                                    ->scalarNode('reply_to')->info('Where replies go; for a tenant without a domain of its own.')->defaultNull()->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('cache')
                    ->info('Scopes cache.app, and every pool built on it, to the active tenant: a sub-namespace and prefixed tags for each tenant.')
                    ->canBeDisabled()
                    ->children()
                        ->arrayNode('global_pools')
                            ->info("The pools built on cache.app that every tenant shares, besides Symfony's own, such as cache.app itself or app.exchange_rates.")
                            ->scalarPrototype()->cannotBeEmpty()->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('row_level_security')
                    ->info('The row-level security of PostgreSQL, behind the Doctrine filter: the active tenant in a session variable of each connection, and the policies that compare each row to it.')
                    ->canBeEnabled()
                    ->children()
                        ->scalarNode('connection')
                            ->info('The Doctrine connection whose sessions carry the active tenant.')
                            ->defaultValue('default')
                            ->cannotBeEmpty()
                        ->end()
                        ->scalarNode('entity_manager')
                            ->info('The entity manager whose tenant-scoped entities get a policy.')
                            ->defaultValue('default')
                            ->cannotBeEmpty()
                        ->end()
                        ->scalarNode('setting')
                            ->info('The session variable that holds the active tenant: two lowercase identifiers joined by a dot.')
                            ->defaultValue(RowLevelSecurityConfig::DEFAULT_SETTING)
                        ->end()
                        ->scalarNode('policy')
                            ->info('The name of the policy on each tenant-scoped table.')
                            ->defaultValue(RowLevelSecurityConfig::DEFAULT_POLICY)
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    #[Override]
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if (!$builder->hasExtension('doctrine')) {
            return;
        }

        // The bundle's own tree, read before its extension loads: the filter must reach DoctrineBundle first.
        /** @var array{doctrine_filter: bool} $config */
        $config = new Processor()->processConfiguration(new Configuration($this, $builder, $this->extensionAlias), $builder->getExtensionConfig($this->extensionAlias));

        if ($config['doctrine_filter']) {
            $container->extension('doctrine', ['orm' => ['filters' => [
                ActiveTenantFilter::NAME => ['class' => ActiveTenantFilter::class, 'enabled' => true],
            ]]]);
        }
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var array{store: string, reachable_tenants: ?string, doctrine_filter: bool, request: array{enabled: bool, selector_route: ?string, ignored_routes: list<string>}, console: bool, hosts: array<string, string>, host_aliases: array<string, string>, redirect_host_aliases: bool, shops: array<string, array{includes: list<string>}>, authentication: array{enabled: bool, user_providers: list<string>, account_sharing: ?string, shared_sessions: bool, shared_tenants: list<list<string>>, tenant_firewalls: array<string, list<string>>, tenant_user_providers: array<string, array{tenants: array<string, string>, default: ?string}>}, mailer: array{settings: ?string, platform_sender: ?string, tenants: array<string, array{transport: ?string, sender: ?string, name: ?string, reply_to: ?string}>}, cache: array{enabled: bool, global_pools: list<string>}, row_level_security: array{enabled: bool, connection: string, entity_manager: string, setting: string, policy: string}} $config */
        $services = $container->services();

        $builder->registerForAutoconfiguration(TenantActivation::class)->addTag(self::ACTIVATION_TAG);
        $services->set(TenantContext::class)
            ->args([tagged_iterator(self::ACTIVATION_TAG)])
            ->tag('kernel.reset', ['method' => 'leave'])
            ->public();
        // Before the resolvers that would give a `#[CurrentTenant]` argument another value: the request attribute of
        // the same name, the entity Doctrine would look up.
        $services->set(CurrentTenantValueResolver::class)
            ->args([service(TenantContext::class)])
            ->tag('controller.argument_value_resolver', ['before' => ['argument_resolver.request_attribute', 'doctrine.orm.entity_value_resolver']]);

        $services->set(DoctrineTenantActivation::class)->args([service('doctrine')->nullOnInvalid()])->tag(self::ACTIVATION_TAG);

        if ($config['doctrine_filter']) {
            // Every entity manager's flush: the guard reads the filter of the manager that flushes.
            $services->set(TenantWriteGuard::class)->tag('doctrine.event_listener', ['event' => 'onFlush']);
        }

        if (interface_exists(MiddlewareInterface::class)) {
            // Autoconfigured: its #[AsMessageMiddleware] puts it on every bus.
            $services->set(TenantMiddleware::class)->args([service(TenantContext::class)])->autoconfigure();
        }

        if ($config['console'] && class_exists(ConsoleEvents::class)) {
            $services->set(TenantOption::class)
                ->args([service(TenantContext::class)])
                ->tag('kernel.event_listener', ['event' => ConsoleEvents::COMMAND, 'method' => 'onCommand'])
                ->tag('kernel.event_listener', ['event' => ConsoleEvents::TERMINATE, 'method' => 'onTerminate']);
        }

        if ($config['cache']['enabled'] && interface_exists(AdapterInterface::class)) {
            // Read by TenantCachePass, once every pool is registered.
            $builder->setParameter(TenantCachePass::GLOBAL_POOLS_PARAMETER, $config['cache']['global_pools']);
        }

        if ($config['row_level_security']['enabled']) {
            $this->loadRowLevelSecurity($config['row_level_security'], $container);
        }

        $this->loadHosts($config['hosts'], $config['host_aliases'], $config['redirect_host_aliases'], $container);
        $this->loadSites($config['shops'], $config['authentication'], $config['mailer'], $container, null !== $config['reachable_tenants'] && $config['request']['enabled']);

        if ('session' === $config['store']) {
            $services->set(SessionActiveTenantStore::class)->args([service('request_stack')]);
            $services->alias(ActiveTenantStore::class, SessionActiveTenantStore::class);
        } else {
            $services->alias(ActiveTenantStore::class, $config['store']);
        }

        if (null === $config['reachable_tenants']) {
            return;
        }

        $services->alias(ReachableTenantsProvider::class, $config['reachable_tenants']);
        $services->set(ActiveTenant::class)
            ->args([service(ActiveTenantStore::class), service(ReachableTenantsProvider::class)])
            ->public();
        $services->set(SwitchActiveTenant::class)
            ->args([service(ActiveTenant::class)])
            ->tag('controller.service_arguments')
            ->public();

        if ($config['request']['enabled']) {
            // After the router, so that the route is known, and after the firewall, so that the reachable tenants can
            // read the authenticated account; the debug container replaces the firewall listener by its traceable
            // one, under another id.
            $services->set(ResolveActiveTenant::class)
                ->args([
                    service(ActiveTenant::class),
                    service(TenantContext::class),
                    service('router'),
                    $config['request']['selector_route'],
                    $config['request']['ignored_routes'],
                    service('security.token_storage')->nullOnInvalid(),
                ])
                ->tag('kernel.event_listener', ['event' => KernelEvents::REQUEST, 'after' => ['router_listener', 'security.firewall', 'debug.security.firewall']]);
        }

        if (class_exists(AsTwigFunction::class)) {
            $services->set(TenancyExtension::class)
                ->args([service(TenantContext::class), service(ReachableTenantsProvider::class)])
                ->tag('twig.attribute_extension')
                ->tag('twig.runtime');
        }
    }

    /**
     * The hosts, the shops, the scoped authentication and the mail of each tenant. Every value is checked as the
     * container compiles, by the constructors that will read it.
     *
     * @param array<string, array{includes: list<string>}>                                                                                                                                                                                                                                          $shops
     * @param array{enabled: bool, user_providers: list<string>, account_sharing: ?string, shared_sessions: bool, shared_tenants: list<list<string>>, tenant_firewalls: array<string, list<string>>, tenant_user_providers: array<string, array{tenants: array<string, string>, default: ?string}>} $authentication
     * @param array{settings: ?string, platform_sender: ?string, tenants: array<string, array{transport: ?string, sender: ?string, name: ?string, reply_to: ?string}>}                                                                                                                              $mailer
     * @param bool                                                                                                                                                                                                                                                                                  $reachableTenantsDecide whether `ResolveActiveTenant` checks the reachable tenants of a signed-in account, after the firewall
     */
    private function loadSites(array $shops, array $authentication, array $mailer, ContainerConfigurator $container, bool $reachableTenantsDecide): void
    {
        $services = $container->services();

        $includes = array_map(static fn (array $shop): array => $shop['includes'], $shops);
        ShopNetwork::fromReferences($includes);
        $services->set(ShopNetwork::class)->factory([ShopNetwork::class, 'fromReferences'])->args([$includes]);
        $services->set(ShopContext::class)->args([service(TenantContext::class), service(ShopNetwork::class)])->public();

        if ($authentication['enabled'] && class_exists(LoginSuccessEvent::class)) {
            new ConfiguredAccountSharing($authentication['shared_tenants']);
            $services->set(ConfiguredAccountSharing::class)->args([$authentication['shared_tenants'], service(ShopNetwork::class), $authentication['shared_sessions']]);
            $services->alias(AccountSharing::class, $authentication['account_sharing'] ?? ConfiguredAccountSharing::class);
            $services->set(RememberSignInTenant::class)
                ->args([service(TenantContext::class)])
                ->tag('kernel.event_listener', ['event' => LoginSuccessEvent::class, 'method' => 'onLoginSuccess'])
                ->tag('kernel.event_listener', ['event' => LogoutEvent::class, 'method' => 'onLogout']);

            foreach ($authentication['user_providers'] as $provider) {
                $services->set('phil_tenancy.scoped_user_provider.'.$provider, TenantScopedUserProvider::class)
                    ->decorate($provider)
                    ->args([service('.inner'), service(TenantContext::class), service('request_stack'), service(AccountSharing::class), $reachableTenantsDecide]);
            }

            $this->loadTenantAuthentication($authentication['tenant_firewalls'], $authentication['tenant_user_providers'], $container);
        }

        $settings = $mailer['settings'];
        $tenants = $mailer['tenants'];

        if ((null === $settings && [] === $tenants) || !interface_exists(TransportInterface::class)) {
            return;
        }

        if (null === $settings) {
            new ConfiguredTenantMailSettings($mailer['platform_sender'], $tenants);
            $services->set(ConfiguredTenantMailSettings::class)->args([$mailer['platform_sender'], $tenants]);
        }

        $services->alias(TenantMailSettings::class, $settings ?? ConfiguredTenantMailSettings::class);
        $services->set(TenantTransports::class)
            ->decorate('mailer.transports', invalidBehavior: ContainerInterface::IGNORE_ON_INVALID_REFERENCE)
            ->args([service('.inner'), service(TenantContext::class), service(TenantMailSettings::class)]);
    }

    /**
     * The tenant of each host and the aliases of the hosts, entered and redirected before the firewall. Every value is
     * checked as the container compiles, by the constructor that will read it.
     *
     * @param array<string, string> $hosts
     * @param array<string, string> $aliases
     */
    private function loadHosts(array $hosts, array $aliases, bool $redirect, ContainerConfigurator $container): void
    {
        if ([] === $hosts && [] === $aliases) {
            return;
        }

        $services = $container->services();
        new ConfiguredHostTenants($hosts, $aliases);
        $services->set(ConfiguredHostTenants::class)->args([$hosts, $aliases]);
        $services->alias(HostTenants::class, ConfiguredHostTenants::class);
        $services->alias(HostAliases::class, ConfiguredHostTenants::class);
        // Before the firewall, so that authentication acts in the host's tenant; the debug container replaces the
        // firewall listener by its traceable one, under another id.
        $services->set(ResolveHostTenant::class)
            ->args([service(HostTenants::class), service(TenantContext::class), service('event_dispatcher')->nullOnInvalid(), service('logger')->nullOnInvalid()])
            ->tag('kernel.event_listener', ['event' => KernelEvents::REQUEST, 'before' => ['security.firewall', 'debug.security.firewall', ResolveActiveTenant::class]]);

        if ($redirect && [] !== $aliases) {
            $services->set(RedirectHostAlias::class)
                ->args([service(HostAliases::class)])
                ->tag('kernel.event_listener', ['event' => KernelEvents::REQUEST, 'before' => [ResolveHostTenant::class, 'security.firewall', 'debug.security.firewall']]);
        }
    }

    /**
     * The request matchers of the firewalls of tenants, and the user providers chosen for each tenant. The tenant
     * references are checked as the container compiles.
     *
     * @param array<string, list<string>>                                            $firewalls
     * @param array<string, array{tenants: array<string, string>, default: ?string}> $providers
     */
    private function loadTenantAuthentication(array $firewalls, array $providers, ContainerConfigurator $container): void
    {
        $services = $container->services();

        foreach ($firewalls as $name => $tenants) {
            new TenantRequestMatcher(new TenantContext(), $tenants);
            $services->set(self::REQUEST_MATCHER_PREFIX.$name, TenantRequestMatcher::class)->args([service(TenantContext::class), $tenants]);
        }

        foreach ($providers as $name => $provider) {
            $locator = [];

            foreach ($provider['tenants'] as $tenant => $id) {
                $locator[TenantId::fromString($tenant)->value] = service($id);
            }

            $services->set(self::USER_PROVIDER_PREFIX.$name, TenantUserProviders::class)
                ->args([service(TenantContext::class), service_locator($locator), null === $provider['default'] ? null : service($provider['default'])]);
        }
    }

    /**
     * The names are checked as the container compiles, by the configuration's own constructor, rather than at the
     * first connection.
     *
     * @param array{enabled: bool, connection: string, entity_manager: string, setting: string, policy: string} $config
     */
    private function loadRowLevelSecurity(array $config, ContainerConfigurator $container): void
    {
        new RowLevelSecurityConfig($config['setting'], $config['policy']);
        $services = $container->services();

        $services->set(RowLevelSecurityConfig::class)->args([$config['setting'], $config['policy']]);
        $services->set(TenantSession::class)
            ->args([service(RowLevelSecurityConfig::class)])
            ->tag(self::ACTIVATION_TAG);
        $services->set(TenantSessionMiddleware::class)
            ->args([service(TenantSession::class)])
            ->tag('doctrine.middleware', ['connection' => $config['connection']]);
        $services->set(RowLevelSecurityPolicies::class)->args([service(RowLevelSecurityConfig::class)]);

        if (class_exists(ConsoleEvents::class)) {
            $entityManager = service(\sprintf('doctrine.orm.%s_entity_manager', $config['entity_manager']));
            $services->set(RowLevelSecurityPoliciesCommand::class)
                ->args([$entityManager, service(RowLevelSecurityPolicies::class)])
                ->tag('console.command');
            $services->set(RowLevelSecurityCheckCommand::class)
                ->args([$entityManager, service(RowLevelSecurityPolicies::class), service(RowLevelSecurityConfig::class)])
                ->tag('console.command');
        }
    }
}

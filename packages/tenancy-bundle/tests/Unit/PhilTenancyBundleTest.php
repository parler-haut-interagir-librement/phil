<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Tests\Unit;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\DoctrineExtension;
use Phil\DddBundle\Domain\Exception\InvalidValue;
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
use Phil\TenancyBundle\PhilTenancyBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Argument\ServiceLocatorArgument;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;

#[CoversClass(PhilTenancyBundle::class)]
final class PhilTenancyBundleTest extends TestCase
{
    public function testTheExtensionAliasIsPhilTenancy(): void
    {
        self::assertSame('phil_tenancy', $this->extension()->getAlias());
    }

    public function testWithoutReachableTenantsOnlyTheContextAndItsAdaptersAreRegistered(): void
    {
        $container = $this->load([]);

        self::assertSame([
            'service_container',
            TenantContext::class,
            CurrentTenantValueResolver::class,
            DoctrineTenantActivation::class,
            TenantWriteGuard::class,
            TenantMiddleware::class,
            TenantOption::class,
            ShopNetwork::class,
            ShopContext::class,
            SessionActiveTenantStore::class,
        ], array_keys($container->getDefinitions()));

        $context = $container->getDefinition(TenantContext::class);
        $iterator = $context->getArgument(0);
        self::assertInstanceOf(TaggedIteratorArgument::class, $iterator);
        self::assertSame(PhilTenancyBundle::ACTIVATION_TAG, $iterator->getTag());
        self::assertSame([['method' => 'leave']], $context->getTag('kernel.reset'));
        self::assertTrue($context->isPublic());

        self::assertSame([['before' => ['argument_resolver.request_attribute', 'doctrine.orm.entity_value_resolver']]], $container->getDefinition(CurrentTenantValueResolver::class)->getTag('controller.argument_value_resolver'));
        self::assertEquals([new Reference(TenantContext::class)], $container->getDefinition(CurrentTenantValueResolver::class)->getArguments());

        $activation = $container->getDefinition(DoctrineTenantActivation::class);
        self::assertEquals([new Reference('doctrine', ContainerInterface::NULL_ON_INVALID_REFERENCE)], $activation->getArguments());
        self::assertTrue($activation->hasTag(PhilTenancyBundle::ACTIVATION_TAG));
        self::assertTrue($container->getAutoconfiguredInstanceof()[TenantActivation::class]->hasTag(PhilTenancyBundle::ACTIVATION_TAG));

        self::assertEquals([new Reference(TenantContext::class)], $container->getDefinition(TenantMiddleware::class)->getArguments());
        self::assertEquals([new Reference(TenantContext::class)], $container->getDefinition(TenantOption::class)->getArguments());
        self::assertSame([
            ['event' => 'console.command', 'method' => 'onCommand'],
            ['event' => 'console.terminate', 'method' => 'onTerminate'],
        ], $container->getDefinition(TenantOption::class)->getTag('kernel.event_listener'));

        self::assertEquals([new Reference('request_stack')], $container->getDefinition(SessionActiveTenantStore::class)->getArguments());
        self::assertSame(SessionActiveTenantStore::class, (string) $container->getAlias(ActiveTenantStore::class));
        self::assertFalse($container->hasAlias(ReachableTenantsProvider::class));
    }

    public function testReachableTenantsRegisterWhatResolvesATenant(): void
    {
        $container = $this->load([['reachable_tenants' => 'app.reachable', 'request' => ['selector_route' => 'select', 'ignored_routes' => ['login']]]]);

        self::assertSame('app.reachable', (string) $container->getAlias(ReachableTenantsProvider::class));

        $activeTenant = $container->getDefinition(ActiveTenant::class);
        self::assertEquals([new Reference(ActiveTenantStore::class), new Reference(ReachableTenantsProvider::class)], $activeTenant->getArguments());
        self::assertTrue($activeTenant->isPublic());

        $switch = $container->getDefinition(SwitchActiveTenant::class);
        self::assertEquals([new Reference(ActiveTenant::class)], $switch->getArguments());
        self::assertTrue($switch->hasTag('controller.service_arguments'));
        self::assertTrue($switch->isPublic());

        $listener = $container->getDefinition(ResolveActiveTenant::class);
        self::assertEquals([new Reference(ActiveTenant::class), new Reference(TenantContext::class), new Reference('router'), 'select', ['login'], new Reference('security.token_storage', ContainerInterface::NULL_ON_INVALID_REFERENCE)], $listener->getArguments());
        self::assertSame([['event' => 'kernel.request', 'after' => ['router_listener', 'security.firewall', 'debug.security.firewall']]], $listener->getTag('kernel.event_listener'));

        $twig = $container->getDefinition(TenancyExtension::class);
        self::assertEquals([new Reference(TenantContext::class), new Reference(ReachableTenantsProvider::class)], $twig->getArguments());
        self::assertTrue($twig->hasTag('twig.attribute_extension'));
        self::assertTrue($twig->hasTag('twig.runtime'));
    }

    public function testTheStoreTheListenerAndTheConsoleOptionAreConfigured(): void
    {
        $container = $this->load([['store' => 'app.store', 'reachable_tenants' => 'app.reachable', 'request' => false, 'console' => false]]);

        self::assertSame('app.store', (string) $container->getAlias(ActiveTenantStore::class));
        self::assertFalse($container->hasDefinition(SessionActiveTenantStore::class));
        self::assertFalse($container->hasDefinition(ResolveActiveTenant::class));
        self::assertFalse($container->hasDefinition(TenantOption::class));
        self::assertTrue($container->hasDefinition(ActiveTenant::class));
    }

    public function testTheFilterIsPrependedToDoctrine(): void
    {
        $container = $this->prepend([]);

        self::assertSame([['orm' => ['filters' => [
            ActiveTenantFilter::NAME => ['class' => ActiveTenantFilter::class, 'enabled' => true],
        ]]]], $container->getExtensionConfig('doctrine'));
    }

    public function testTheFilterCanBeLeftToTheApplication(): void
    {
        self::assertSame([], $this->prepend([['doctrine_filter' => false]])->getExtensionConfig('doctrine'));
    }

    public function testTheWriteGuardListensToEveryFlushWhileTheFilterIsTheBundles(): void
    {
        self::assertSame([['event' => 'onFlush']], $this->load([])->getDefinition(TenantWriteGuard::class)->getTag('doctrine.event_listener'));
        self::assertFalse($this->load([['doctrine_filter' => false]])->hasDefinition(TenantWriteGuard::class));
    }

    public function testWithoutDoctrineNothingIsPrepended(): void
    {
        $container = new ContainerBuilder();
        $extension = $this->extension();
        $container->registerExtension($extension);
        // A configuration the bundle refuses is not even read.
        $container->prependExtensionConfig('phil_tenancy', ['unknown' => true]);

        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $extension->prepend($container);

        self::assertSame([['unknown' => true]], $container->getExtensionConfig('phil_tenancy'));
    }

    public function testTheCachePassReadsTheGlobalPoolsTheApplicationNames(): void
    {
        self::assertSame([], $this->load([])->getParameter(TenantCachePass::GLOBAL_POOLS_PARAMETER));
        self::assertSame(['app.rates'], $this->load([['cache' => ['global_pools' => ['app.rates']]]])->getParameter(TenantCachePass::GLOBAL_POOLS_PARAMETER));
    }

    public function testTheCacheCanBeLeftAlone(): void
    {
        self::assertFalse($this->load([['cache' => false]])->hasParameter(TenantCachePass::GLOBAL_POOLS_PARAMETER));
    }

    public function testRowLevelSecurityIsOffByDefault(): void
    {
        $container = $this->load([]);

        self::assertFalse($container->hasDefinition(TenantSession::class));
        self::assertFalse($container->hasDefinition(TenantSessionMiddleware::class));
        self::assertFalse($container->hasDefinition(RowLevelSecurityCheckCommand::class));
    }

    public function testRowLevelSecuritySetsTheTenantOnTheSessionsOfItsConnection(): void
    {
        $container = $this->load([['row_level_security' => ['enabled' => true, 'connection' => 'tenants', 'entity_manager' => 'tenants', 'setting' => 'app.tenant', 'policy' => 'tenant_rows']]]);

        self::assertSame(['app.tenant', 'tenant_rows'], $container->getDefinition(RowLevelSecurityConfig::class)->getArguments());
        self::assertEquals([new Reference(RowLevelSecurityConfig::class)], $container->getDefinition(TenantSession::class)->getArguments());
        self::assertTrue($container->getDefinition(TenantSession::class)->hasTag(PhilTenancyBundle::ACTIVATION_TAG));
        self::assertEquals([new Reference(TenantSession::class)], $container->getDefinition(TenantSessionMiddleware::class)->getArguments());
        self::assertSame([['connection' => 'tenants']], $container->getDefinition(TenantSessionMiddleware::class)->getTag('doctrine.middleware'));
        self::assertEquals([new Reference(RowLevelSecurityConfig::class)], $container->getDefinition(RowLevelSecurityPolicies::class)->getArguments());

        $manager = new Reference('doctrine.orm.tenants_entity_manager');
        self::assertEquals([$manager, new Reference(RowLevelSecurityPolicies::class)], $container->getDefinition(RowLevelSecurityPoliciesCommand::class)->getArguments());
        self::assertEquals([$manager, new Reference(RowLevelSecurityPolicies::class), new Reference(RowLevelSecurityConfig::class)], $container->getDefinition(RowLevelSecurityCheckCommand::class)->getArguments());
        self::assertTrue($container->getDefinition(RowLevelSecurityPoliciesCommand::class)->hasTag('console.command'));
        self::assertTrue($container->getDefinition(RowLevelSecurityCheckCommand::class)->hasTag('console.command'));
    }

    public function testRowLevelSecurityDefaultsToTheDefaultConnection(): void
    {
        $container = $this->load([['row_level_security' => true]]);

        self::assertSame(['phil.tenant_id', 'phil_tenant_isolation'], $container->getDefinition(RowLevelSecurityConfig::class)->getArguments());
        self::assertSame([['connection' => 'default']], $container->getDefinition(TenantSessionMiddleware::class)->getTag('doctrine.middleware'));
        self::assertEquals(new Reference('doctrine.orm.default_entity_manager'), $container->getDefinition(RowLevelSecurityCheckCommand::class)->getArgument(0));
    }

    public function testTheAliasesOfTheHostsAreRedirectedBeforeTheirTenantIsEntered(): void
    {
        $container = $this->load([['hosts' => ['rose.example' => 'rose'], 'host_aliases' => ['www.rose.example' => 'rose.example']]]);

        self::assertSame([['rose.example' => 'rose'], ['www.rose.example' => 'rose.example']], $container->getDefinition(ConfiguredHostTenants::class)->getArguments());
        self::assertEquals([new Reference(HostAliases::class)], $container->getDefinition(RedirectHostAlias::class)->getArguments());
        self::assertSame([['event' => 'kernel.request', 'before' => [ResolveHostTenant::class, 'security.firewall', 'debug.security.firewall']]], $container->getDefinition(RedirectHostAlias::class)->getTag('kernel.event_listener'));
    }

    public function testTheAliasesServeTheirTenantWithoutARedirect(): void
    {
        $container = $this->load([['hosts' => ['rose.example' => 'rose'], 'host_aliases' => ['www.rose.example' => 'rose.example'], 'redirect_host_aliases' => false]]);

        self::assertFalse($container->hasDefinition(RedirectHostAlias::class));
        self::assertTrue($container->hasDefinition(ResolveHostTenant::class));
    }

    public function testTheNamesOfAliasesFirewallsAndProvidersKeepTheirDashes(): void
    {
        $container = $this->load([[
            'hosts' => ['rose-bud.example' => 'rose'],
            'host_aliases' => ['www-rose-bud.example' => 'rose-bud.example'],
            'authentication' => [
                'tenant_firewalls' => ['club-b' => ['b']],
                'tenant_user_providers' => ['club-members' => ['tenants' => ['b' => 'app.b']]],
            ],
        ]]);

        self::assertSame([['rose-bud.example' => 'rose'], ['www-rose-bud.example' => 'rose-bud.example']], $container->getDefinition(ConfiguredHostTenants::class)->getArguments());
        self::assertTrue($container->hasDefinition(PhilTenancyBundle::REQUEST_MATCHER_PREFIX.'club-b'));
        self::assertTrue($container->hasDefinition(PhilTenancyBundle::USER_PROVIDER_PREFIX.'club-members'));
    }

    public function testAnAliasOfNoHostStopsTheCompilation(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('which is not a host of a tenant');

        $this->load([['host_aliases' => ['www.rose.example' => 'rose.example']]]);
    }

    public function testTheFirewallsAndUserProvidersOfTheTenants(): void
    {
        $container = $this->load([['authentication' => [
            'tenant_firewalls' => ['club_b' => ['b', 'b-junior']],
            'tenant_user_providers' => [
                'members' => ['tenants' => ['a' => 'security.user.provider.concrete.a', 'b-junior' => 'app.juniors'], 'default' => 'app.staff'],
                'strict' => ['tenants' => ['a' => 'app.a']],
            ],
        ]]]);

        $matcher = $container->getDefinition(PhilTenancyBundle::REQUEST_MATCHER_PREFIX.'club_b');
        self::assertSame(TenantRequestMatcher::class, $matcher->getClass());
        self::assertEquals([new Reference(TenantContext::class), ['b', 'b-junior']], $matcher->getArguments());

        $members = $container->getDefinition(PhilTenancyBundle::USER_PROVIDER_PREFIX.'members');
        self::assertSame(TenantUserProviders::class, $members->getClass());
        self::assertEquals(new Reference(TenantContext::class), $members->getArgument(0));
        $locator = $members->getArgument(1);
        self::assertInstanceOf(ServiceLocatorArgument::class, $locator);
        self::assertEquals(['a' => new Reference('security.user.provider.concrete.a'), 'b-junior' => new Reference('app.juniors')], $locator->getValues());
        self::assertEquals(new Reference('app.staff'), $members->getArgument(2));
        self::assertNull($container->getDefinition(PhilTenancyBundle::USER_PROVIDER_PREFIX.'strict')->getArgument(2));
        self::assertTrue($container->hasDefinition(RememberSignInTenant::class), 'The tenant firewalls enable the authentication.');
    }

    public function testAFirewallOfNoTenantStopsTheCompilation(): void
    {
        $this->expectException(InvalidValue::class);

        $this->load([['authentication' => ['tenant_firewalls' => ['nobody' => []]]]]);
    }

    public function testAProviderOfAMalformedTenantStopsTheCompilation(): void
    {
        $this->expectException(InvalidValue::class);

        $this->load([['authentication' => ['tenant_user_providers' => ['members' => ['tenants' => [' a' => 'app.a']]]]]]);
    }

    public function testASettingThatPostgreSqlWouldRefuseStopsTheCompilation(): void
    {
        $this->expectException(InvalidValue::class);

        $this->load([['row_level_security' => ['enabled' => true, 'setting' => 'tenant']]]);
    }

    public function testEachHostEntersItsTenantBeforeTheFirewall(): void
    {
        $container = $this->load([['hosts' => ['Rose.Example' => 'rose', 'shop-general.example' => 'general']]]);

        self::assertSame([['Rose.Example' => 'rose', 'shop-general.example' => 'general'], []], $container->getDefinition(ConfiguredHostTenants::class)->getArguments());
        self::assertSame(ConfiguredHostTenants::class, (string) $container->getAlias(HostTenants::class));
        self::assertSame(ConfiguredHostTenants::class, (string) $container->getAlias(HostAliases::class));
        self::assertFalse($container->hasDefinition(RedirectHostAlias::class), 'No alias to redirect.');
        self::assertEquals([new Reference(HostTenants::class), new Reference(TenantContext::class), new Reference('event_dispatcher', ContainerInterface::NULL_ON_INVALID_REFERENCE), new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE)], $container->getDefinition(ResolveHostTenant::class)->getArguments());
        self::assertSame([['event' => 'kernel.request', 'before' => ['security.firewall', 'debug.security.firewall', ResolveActiveTenant::class]]], $container->getDefinition(ResolveHostTenant::class)->getTag('kernel.event_listener'));
    }

    public function testWithoutHostsNoHostIsResolved(): void
    {
        self::assertFalse($this->load([])->hasDefinition(ResolveHostTenant::class));
    }

    public function testAMalformedHostStopsTheCompilation(): void
    {
        $this->expectException(InvalidValue::class);

        $this->load([['hosts' => ['shop.example:8443' => 'general']]]);
    }

    public function testTheShopNetworkIsBuiltFromTheShops(): void
    {
        $container = $this->load([['shops' => ['general' => ['includes' => ['rose', 'tavern-qc']], 'rose' => null]]]);

        $network = $container->getDefinition(ShopNetwork::class);
        self::assertSame([ShopNetwork::class, 'fromReferences'], $network->getFactory());
        self::assertSame([['general' => ['rose', 'tavern-qc'], 'rose' => []]], $network->getArguments());
        self::assertEquals([new Reference(TenantContext::class), new Reference(ShopNetwork::class)], $container->getDefinition(ShopContext::class)->getArguments());
        self::assertTrue($container->getDefinition(ShopContext::class)->isPublic());
    }

    public function testAShopReferenceKeepsItsDashes(): void
    {
        $container = $this->load([['shops' => ['tavern-qc' => ['includes' => ['rose-bud']]]]]);

        self::assertSame([['tavern-qc' => ['rose-bud']]], $container->getDefinition(ShopNetwork::class)->getArguments());
    }

    public function testAMalformedShopNetworkStopsTheCompilation(): void
    {
        $this->expectException(InvalidValue::class);

        $this->load([['shops' => ['general' => ['includes' => ['general']]]]]);
    }

    public function testAuthenticationIsNotScopedByDefault(): void
    {
        $container = $this->load([]);

        self::assertFalse($container->hasDefinition(RememberSignInTenant::class));
        self::assertFalse($container->hasDefinition(ConfiguredAccountSharing::class));
    }

    public function testScopedAuthenticationDecoratesEachUserProvider(): void
    {
        $container = $this->load([['authentication' => ['user_providers' => ['security.user.provider.concrete.members', 'app.staff'], 'shared_tenants' => [['fair', 'fair-backstage']]]]]);

        self::assertEquals([[['fair', 'fair-backstage']], new Reference(ShopNetwork::class), false], $container->getDefinition(ConfiguredAccountSharing::class)->getArguments());
        self::assertSame(ConfiguredAccountSharing::class, (string) $container->getAlias(AccountSharing::class));
        self::assertEquals([new Reference(TenantContext::class)], $container->getDefinition(RememberSignInTenant::class)->getArguments());
        self::assertSame([
            ['event' => LoginSuccessEvent::class, 'method' => 'onLoginSuccess'],
            ['event' => LogoutEvent::class, 'method' => 'onLogout'],
        ], $container->getDefinition(RememberSignInTenant::class)->getTag('kernel.event_listener'));

        foreach (['security.user.provider.concrete.members', 'app.staff'] as $provider) {
            $decorator = $container->getDefinition('phil_tenancy.scoped_user_provider.'.$provider);
            self::assertSame(TenantScopedUserProvider::class, $decorator->getClass());
            self::assertSame([$provider, null, 0], $decorator->getDecoratedService());
            self::assertEquals([new Reference('.inner'), new Reference(TenantContext::class), new Reference('request_stack'), new Reference(AccountSharing::class), false], $decorator->getArguments());
        }
    }

    public function testSharedSessionsAndTheReachableTenantsReachTheScopedSessions(): void
    {
        $container = $this->load([['authentication' => ['user_providers' => ['app.staff'], 'shared_sessions' => true], 'reachable_tenants' => 'app.reachable']]);

        self::assertTrue($container->getDefinition(ConfiguredAccountSharing::class)->getArgument(2));
        self::assertTrue($container->getDefinition('phil_tenancy.scoped_user_provider.app.staff')->getArgument(4));
        self::assertFalse($this->load([['authentication' => ['user_providers' => ['app.staff']], 'reachable_tenants' => 'app.reachable', 'request' => ['enabled' => false]]])->getDefinition('phil_tenancy.scoped_user_provider.app.staff')->getArgument(4), 'no listener checks them');
    }

    public function testTheApplicationMayShareAccountsItself(): void
    {
        $container = $this->load([['authentication' => ['account_sharing' => 'app.sharing']]]);

        self::assertSame('app.sharing', (string) $container->getAlias(AccountSharing::class));
    }

    public function testAMalformedSharedTenantStopsTheCompilation(): void
    {
        $this->expectException(InvalidValue::class);

        $this->load([['authentication' => ['shared_tenants' => [['fair', '']]]]]);
    }

    public function testTheMailOfEachTenantDecoratesTheMailersTransports(): void
    {
        $tenants = ['fair-qc' => ['transport' => 'fair_smtp', 'name' => 'Fair', 'reply_to' => 'fair@mail.example', 'sender' => null]];
        $container = $this->load([['mailer' => ['platform_sender' => 'mail@platform.example', 'tenants' => ['fair-qc' => ['transport' => 'fair_smtp', 'name' => 'Fair', 'reply_to' => 'fair@mail.example']]]]]);

        self::assertSame(['mail@platform.example', $tenants], $container->getDefinition(ConfiguredTenantMailSettings::class)->getArguments());
        self::assertSame(ConfiguredTenantMailSettings::class, (string) $container->getAlias(TenantMailSettings::class));
        $transports = $container->getDefinition(TenantTransports::class);
        self::assertSame(['mailer.transports', null, 0, ContainerInterface::IGNORE_ON_INVALID_REFERENCE], $transports->getDecoratedService());
        self::assertEquals([new Reference('.inner'), new Reference(TenantContext::class), new Reference(TenantMailSettings::class)], $transports->getArguments());
    }

    public function testTheApplicationMayGiveTheMailSettingsItself(): void
    {
        $container = $this->load([['mailer' => ['settings' => 'app.mail_settings']]]);

        self::assertFalse($container->hasDefinition(ConfiguredTenantMailSettings::class));
        self::assertSame('app.mail_settings', (string) $container->getAlias(TenantMailSettings::class));
        self::assertTrue($container->hasDefinition(TenantTransports::class));
    }

    public function testWithoutMailSettingsTheTransportsAreLeftAlone(): void
    {
        $container = $this->load([['mailer' => ['platform_sender' => 'mail@platform.example']]]);

        self::assertFalse($container->hasDefinition(TenantTransports::class));
        self::assertFalse($container->hasAlias(TenantMailSettings::class));
    }

    public function testAMalformedSenderStopsTheCompilation(): void
    {
        $this->expectException(InvalidValue::class);

        $this->load([['mailer' => ['tenants' => ['fair' => ['reply_to' => 'fair@mail.example']]]]]);
    }

    /**
     * @param list<array<string, mixed>> $configs
     */
    private function load(array $configs): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $this->extension()->load($configs, $container);

        return $container;
    }

    /**
     * @param list<array<string, mixed>> $configs
     */
    private function prepend(array $configs): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new DoctrineExtension());

        $extension = $this->extension();
        $container->registerExtension($extension);

        foreach ($configs as $config) {
            $container->loadFromExtension('phil_tenancy', $config);
        }

        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $extension->prepend($container);

        return $container;
    }

    private function extension(): ExtensionInterface
    {
        $extension = new PhilTenancyBundle()->getContainerExtension();
        self::assertNotNull($extension);

        return $extension;
    }
}

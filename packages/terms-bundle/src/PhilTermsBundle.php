<?php

declare(strict_types=1);

namespace Phil\TermsBundle;

use Override;
use Phil\TermsBundle\Infrastructure\DependencyInjection\KitSubdirectoryPass;
use Phil\TermsBundle\Infrastructure\DependencyInjection\TermsServices;
use Phil\TermsBundle\Infrastructure\Notifications\NotificationsNotifier;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * Versioned terms of use for a Symfony application, wired from its configuration:
 *
 * ```yaml
 * phil_terms:
 *     locales: [en, fr]
 *     member_source: app.members
 *     stable_identifiers: true
 *     policies:
 *         terms: { required: true }
 *         privacy: { required: true, renew_after_days: 365 }
 *         code_of_conduct: { required: false }
 *         contributions: { required: true, contribution_licence: true }
 *     admin: { enabled: true, role: ROLE_LEGAL }
 *     network: true
 *     notifications: true
 *     reminders: { enabled: true, frequency: '1 week' }
 * ```
 *
 * The versions are kept in `phil_terms_version` and the acceptances in `phil_terms_acceptance`, both added to the
 * schema the ORM generates. The gate sends a signed-in member to the acceptance page while a required policy has a
 * version for them to accept; the public pages, the administration, the signup checkbox, the console, the notices, the
 * reminders and the privacy export are wired when their packages are installed and their options enabled. The pages draw
 * their parts with recipes of the PHIL UX kit — or the Shadcn kit — installed by the Symfony UX Toolkit (`RECIPES`).
 */
final class PhilTermsBundle extends AbstractBundle
{
    public const string LAYOUT = '@PhilTerms/layout.html.twig';

    public const string ADMIN_LAYOUT = '@PhilTerms/admin/layout.html.twig';

    /**
     * The recipes of the kit the templates draw, as `ux:install` names them.
     */
    public const array RECIPES = ['alert', 'badge', 'button', 'checkbox', 'field', 'input', 'native-select', 'table', 'textarea'];

    #[Override]
    protected string $extensionAlias = 'phil_terms';

    /**
     * The recipes of the kit are named after the components subdirectory of the Toolkit.
     */
    #[Override]
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new KitSubdirectoryPass());
    }

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('connection')->info('The DBAL connection of the two tables.')->defaultValue('default')->cannotBeEmpty()->end()
                ->scalarNode('clock')->info('Service id of the PSR-20 clock.')->defaultValue('clock')->cannotBeEmpty()->end()
                ->scalarNode('default_locale')->info("The locale whose text a version shows when it has none in the request's locale.")->defaultValue('en')->cannotBeEmpty()->end()
                ->arrayNode('locales')->info('The locales the administration asks a text for; the default locale alone by default.')->scalarPrototype()->cannotBeEmpty()->end()->end()
                ->scalarNode('timezone')->info('The time zone in which the administration reads the moment a version takes effect and shows the moments of the versions; they are kept in UTC.')->defaultValue('UTC')->cannotBeEmpty()->end()
                ->scalarNode('kit_subdirectory')->info('Where the recipes of the kit are, under the components directory; the subdirectory of "ux_toolkit" when null. To set in an environment without the Toolkit.')->defaultNull()->end()
                ->arrayNode('policies')
                    ->info('The policies, by key: whether a signed-in member must have accepted the version in force to go on, after how many days an acceptance is asked again, and whether its versions name the licence of the contributions.')
                    ->useAttributeAsKey('key')
                    ->normalizeKeys(false)
                    ->arrayPrototype()
                        ->children()
                            ->booleanNode('required')->defaultTrue()->end()
                            ->integerNode('renew_after_days')->min(1)->max(3650)->defaultNull()->end()
                            ->booleanNode('contribution_licence')->info("Whether each version of the policy names the licence of the members' contributions; one policy at most.")->defaultFalse()->end()
                        ->end()
                    ->end()
                ->end()
                ->scalarNode('member_resolver')->info('Service id of the MemberResolver; by default, the user identifier of the security token, which needs stable_identifiers.')->defaultNull()->end()
                ->booleanNode('stable_identifiers')->info('Declares that the user identifiers of the security token are never email addresses, so that the default resolver may name the members by them.')->defaultFalse()->end()
                ->scalarNode('member_source')->info('Service id of the MemberSource every member is read from, those who never accepted anything included.')->isRequired()->cannotBeEmpty()->end()
                ->scalarNode('notifier')->info('Service id of a TermsNotifier, instead of the one of "notifications".')->defaultNull()->end()
                ->arrayNode('gate')
                    ->info('Sends a signed-in member who has a version to accept to the acceptance page; an API is answered 403.')
                    ->canBeDisabled()
                    ->children()
                        ->arrayNode('exempt_routes')->info("Routes the gate lets through, on top of the bundle's own.")->scalarPrototype()->cannotBeEmpty()->end()->end()
                        ->arrayNode('exempt_paths')->info('Prefixes of paths the gate lets through.')->scalarPrototype()->cannotBeEmpty()->end()->defaultValue(['/_wdt', '/_profiler', '/_error'])->end()
                        ->arrayNode('api_paths')->info('Prefixes of the paths answered with a problem in JSON rather than sent to the page.')->scalarPrototype()->cannotBeEmpty()->end()->end()
                        ->scalarNode('problem_type')->info('The "type" URI of the problem an API is answered with.')->defaultValue('about:blank')->cannotBeEmpty()->end()
                    ->end()
                ->end()
                ->arrayNode('network')
                    ->info('Keeps the network an acceptance came from, with phil/ip-anonymizer — never the address itself.')
                    ->canBeEnabled()
                    ->children()
                        ->integerNode('ipv4_prefix')->min(0)->max(32)->defaultValue(24)->end()
                        ->integerNode('ipv6_prefix')->min(0)->max(128)->defaultValue(48)->end()
                    ->end()
                ->end()
                ->arrayNode('pages')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('layout')->info('The Twig layout the acceptance and public pages extend; it defines the "title" and "content" blocks.')->defaultValue(self::LAYOUT)->cannotBeEmpty()->end()
                    ->end()
                ->end()
                ->arrayNode('admin')
                    ->info('The administration that publishes the versions, mounted from the routes of the bundle behind the firewall.')
                    ->canBeEnabled()
                    ->children()
                        ->scalarNode('role')->defaultValue('ROLE_ADMIN')->cannotBeEmpty()->end()
                        ->scalarNode('layout')->defaultValue(self::ADMIN_LAYOUT)->cannotBeEmpty()->end()
                    ->end()
                ->end()
                ->arrayNode('notifications')
                    ->info('The notices of phil/notifications-bundle: a material version published, a reminder; the bundle declares both types.')
                    ->canBeEnabled()
                    ->children()
                        ->scalarNode('published_type')->defaultValue(NotificationsNotifier::PUBLISHED_TYPE)->cannotBeEmpty()->end()
                        ->scalarNode('reminder_type')->defaultValue(NotificationsNotifier::REMINDER_TYPE)->cannotBeEmpty()->end()
                    ->end()
                ->end()
                ->arrayNode('reminders')
                    ->info('The reminders sent on the schedule "phil_terms" to the members who will not be in order some days from now.')
                    ->canBeEnabled()
                    ->children()
                        ->scalarNode('frequency')->defaultValue('1 week')->cannotBeEmpty()->end()
                        ->integerNode('days_ahead')->min(0)->max(365)->defaultValue(14)->end()
                    ->end()
                ->end()
            ->end();
    }

    /**
     * Declares the types of the notices to the notifications bundle when the notices are enabled, so that the
     * application has nothing to write for them.
     */
    #[Override]
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if (!$builder->hasExtension('phil_notifications')) {
            return;
        }

        $types = $this->notificationTypes($builder);

        if ([] !== $types) {
            $container->extension('phil_notifications', ['types' => array_fill_keys($types, ['channels' => ['site', 'email']])]);
        }
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @phpstan-var array{connection: string, clock: string, default_locale: string, locales: list<string>, timezone: string, kit_subdirectory: string|null, policies: array<array-key, array{required: bool, renew_after_days: int|null, contribution_licence: bool}>, member_resolver: string|null, stable_identifiers: bool, member_source: string, notifier: string|null, gate: array{enabled: bool, exempt_routes: list<string>, exempt_paths: list<string>, api_paths: list<string>, problem_type: string}, network: array{enabled: bool, ipv4_prefix: int, ipv6_prefix: int}, pages: array{layout: string}, admin: array{enabled: bool, role: string, layout: string}, notifications: array{enabled: bool, published_type: string, reminder_type: string}, reminders: array{enabled: bool, frequency: string, days_ahead: int}} $config */
        TermsServices::register($builder, $config);
    }

    /**
     * The two types the configuration of the application names, the last one given of each, or none when the notices
     * are off.
     *
     * @return array<string, string>
     */
    private function notificationTypes(ContainerBuilder $builder): array
    {
        $enabled = false;
        $types = ['published_type' => NotificationsNotifier::PUBLISHED_TYPE, 'reminder_type' => NotificationsNotifier::REMINDER_TYPE];

        foreach ($builder->getExtensionConfig($this->extensionAlias) as $config) {
            /** @var array<string, mixed> $config */
            if (!\array_key_exists('notifications', $config)) {
                continue;
            }

            $notifications = $config['notifications'];
            $enabled = \is_array($notifications) ? false !== ($notifications['enabled'] ?? true) : false !== $notifications;

            foreach (array_keys($types) as $key) {
                if (\is_array($notifications) && \is_string($notifications[$key] ?? null)) {
                    $types[$key] = $notifications[$key];
                }
            }
        }

        return $enabled ? $types : [];
    }
}

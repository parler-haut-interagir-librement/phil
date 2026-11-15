<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle;

use Override;
use Phil\NewsletterBundle\Domain\ValueObject\Site;
use Phil\NewsletterBundle\Infrastructure\DependencyInjection\NewsletterServices;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * A site's newsletter: lists, double opt-in, campaigns written in Markdown and laid out by the house layout or by
 * templates rendered in a sandbox, sending through Symfony Mailer at the pace of sender profiles, one-click
 * unsubscription, a preference center, bounces from an IMAP mailbox and from providers' webhooks, clicks counted in a
 * total by default, audience webhooks, and the send and list actions of `phil/automations`.
 *
 * ```yaml
 * phil_newsletter:
 *     secret: '%env(NEWSLETTER_SECRET)%'
 *     sites:
 *         default: { base_url: 'https://example.com' }
 *     profiles:
 *         default: { from: 'news@example.com', from_name: 'Example' }
 * ```
 */
final class PhilNewsletterBundle extends AbstractBundle
{
    public const string ASSETS_DIR = __DIR__.'/../assets';

    #[Override]
    protected string $extensionAlias = 'phil_newsletter';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->booleanNode('enabled')->info("Registers the bundle's services.")->defaultTrue()->end()
                ->scalarNode('secret')
                    ->info('The key of the signed links, the VERP tags and the suppression hashes: at least 16 bytes, kept secret; a new key breaks the links of every message sent.')
                    ->defaultValue('%kernel.secret%')
                ->end()
                ->scalarNode('connection')->info('The Doctrine DBAL connection of the tables.')->defaultValue('doctrine.dbal.default_connection')->end()
                ->booleanNode('check_mail_domain')->info("Whether the worker that sends the confirmation of a double opt-in asks the DNS that the address's domain receives mail first, and refuses the subscription when it does not; the request that subscribes never waits on the DNS.")->defaultTrue()->end()
                ->scalarNode('site')->info('The site of a standalone application; an application that serves several replaces the CurrentSite service.')->defaultValue(Site::DEFAULT)->end()
                ->arrayNode('sites')
                    ->info("Each site's base address, written into the links of messages that workers send without a request.")
                    ->useAttributeAsKey('key')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('base_url')->isRequired()->cannotBeEmpty()->end()
                        ->end()
                    ->end()
                ->end()
                ->scalarNode('default_profile')->defaultValue('default')->end()
                ->arrayNode('profiles')
                    ->info('The sender profiles: each a sender, one Mailer transport, a send window, a daily ceiling and a pace per recipient domain.')
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('from')->isRequired()->cannotBeEmpty()->end()
                            ->scalarNode('from_name')->defaultValue('')->end()
                            ->scalarNode('reply_to')->defaultNull()->end()
                            ->scalarNode('transport')->info('A transport of framework.mailer.transports, chosen with X-Transport.')->defaultNull()->end()
                            ->scalarNode('unsubscribe_mailbox')->info('An address that also takes unsubscriptions, added to List-Unsubscribe.')->defaultNull()->end()
                            ->arrayNode('window')
                                ->addDefaultsIfNotSet()
                                ->children()
                                    ->arrayNode('days')->integerPrototype()->end()->defaultValue([1, 2, 3, 4, 5, 6, 7])->end()
                                    ->scalarNode('from')->defaultValue('00:00')->end()
                                    ->scalarNode('until')->defaultValue('24:00')->end()
                                    ->scalarNode('timezone')->defaultValue('UTC')->end()
                                ->end()
                            ->end()
                            ->integerNode('daily_cap')->defaultNull()->min(1)->end()
                            ->integerNode('rate')->info('Messages per minute to one recipient domain.')->defaultValue(120)->min(1)->end()
                            ->arrayNode('domain_rates')->info('Messages per minute to the domains named, gmail.com: 60.')->useAttributeAsKey('domain')->integerPrototype()->min(1)->end()->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('clicks')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('minimum_seconds')->info("A click this soon after the message was sent is a machine's, and is not counted.")->defaultValue(10)->min(0)->end()
                    ->end()
                ->end()
                ->arrayNode('bounces')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('soft_threshold')->info('Soft bounces after which an address counts as bounced.')->defaultValue(3)->min(1)->end()
                        ->arrayNode('verp')
                            ->info('The return path of every message, bounces+<signed delivery>@domain, so that a bounce names its delivery.')
                            ->canBeEnabled()
                            ->children()
                                ->scalarNode('local_part')->defaultValue('bounces')->end()
                                ->scalarNode('domain')->defaultValue('example.com')->end()
                                ->scalarNode('separator')->defaultValue('+')->end()
                            ->end()
                        ->end()
                        ->arrayNode('imap')
                            ->info('The bounce mailbox, read every five minutes by the schedule "newsletter", over implicit TLS.')
                            ->canBeEnabled()
                            ->children()
                                ->scalarNode('host')->defaultValue('')->end()
                                ->integerNode('port')->defaultValue(993)->end()
                                ->scalarNode('user')->defaultValue('')->end()
                                ->scalarNode('password')->defaultValue('')->end()
                                ->scalarNode('folder')->defaultValue('INBOX')->end()
                                ->booleanNode('delete')->defaultFalse()->end()
                            ->end()
                        ->end()
                        ->scalarNode('remote_event_consumer')->info('The name of the webhook route of the mail provider whose events the bundle consumes.')->defaultNull()->end()
                    ->end()
                ->end()
                ->scalarNode('rate_limiter_storage')->info('The cache pool the pace of sending is counted in, shared by the workers.')->defaultValue('cache.rate_limiter')->end()
                ->arrayNode('webhooks')
                    ->info('Receivers of the audience events, signed by phil/standard-webhooks-bundle.')
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('url')->isRequired()->cannotBeEmpty()->end()
                            ->arrayNode('events')->info('The event types sent; none for all.')->scalarPrototype()->end()->end()
                        ->end()
                    ->end()
                ->end()
                ->scalarNode('preview_role')->info('The role the preview of the Markdown editor answers.')->defaultValue('ROLE_ADMIN')->end()
            ->end();
    }

    #[Override]
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if ($builder->hasExtension('asset_mapper')) {
            $container->extension('asset_mapper', ['paths' => [self::ASSETS_DIR.'/src' => '@phil/newsletter-bundle']]);
        }
    }

    /**
     * @param array<array-key, mixed> $config the configuration, processed by the tree above
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $builder->setParameter('phil_newsletter.enabled', true === $config['enabled']);

        if (true === $config['enabled']) {
            NewsletterServices::load($config, $container, $builder);
        }
    }
}

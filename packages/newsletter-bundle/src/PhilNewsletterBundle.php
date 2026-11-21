<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle;

use Override;
use Phil\NewsletterBundle\Domain\ValueObject\Site;
use Phil\NewsletterBundle\Infrastructure\DependencyInjection\NewsletterServices;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * A site's newsletter: lists, double opt-in, campaigns written in Markdown and laid out by the house layout or by
 * templates rendered in a sandbox, sending through Symfony Mailer at the pace of sender profiles, one-click
 * unsubscription, a preference center, bounces from an IMAP mailbox and from providers' webhooks, clicks counted in a
 * total by default, audience webhooks, and the send and list actions of `phil/automations`. A campaign is sent once
 * approved, under a send run that its circuit breaker pauses when transport failures, bounces or complaints climb.
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
                ->scalarNode('rate_limiter_storage')->info('The cache pool the pace of sending and the limits of the subscription form are counted in, shared by the workers.')->defaultValue('cache.rate_limiter')->end()
                ->arrayNode('subscription')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('rate_limit')
                            ->info('The limits of the public subscription form, per client and per address typed, and the delay before another confirmation of a double opt-in goes to one subscriber; "rate_limit: false" turns them off.')
                            ->canBeDisabled()
                            ->children()
                                ->append($this->limit('per_ip', 'The subscriptions per client address, or IPv6 /64 network, in each interval.', 10, '1 hour'))
                                ->append($this->limit('per_address', 'The subscriptions per address typed, on each site, subscribed or not, in each interval.', 5, '1 hour'))
                                ->scalarNode('resend_delay')->info('The delay before another confirmation goes to the same subscriber, in the format of the Rate Limiter component.')->defaultValue('5 minutes')->cannotBeEmpty()->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
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
                ->arrayNode('approval')
                    ->info('Whether a campaign is sent only once approved, and by whom.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('required')->info('Whether a campaign is sent, or started at its time, only once approved.')->defaultTrue()->end()
                        ->scalarNode('role')->info('The role that approves campaigns.')->defaultValue('ROLE_NEWSLETTER_APPROVER')->cannotBeEmpty()->end()
                        ->booleanNode('four_eyes')->info('Whether the approver must be someone other than the author of the campaign.')->defaultFalse()->end()
                    ->end()
                ->end()
                ->arrayNode('circuit_breaker')
                    ->info('Pauses a send run once, of at least "minimum" messages tried since it started or was resumed, the share of transport failures, bounces or complaints crosses its threshold, in percent; an operator resumes it. "circuit_breaker: false" turns it off.')
                    ->canBeDisabled()
                    ->children()
                        ->integerNode('minimum')->info('The messages tried before the shares are read.')->defaultValue(100)->min(1)->end()
                        ->floatNode('transport_failure_rate')->info('The share of attempts the mail transport refused.')->defaultValue(10.0)->min(0.0)->max(100.0)->end()
                        ->floatNode('bounce_rate')->info('The share of messages sent that bounced.')->defaultValue(5.0)->min(0.0)->max(100.0)->end()
                        ->floatNode('complaint_rate')->info('The share of messages sent reported as spam.')->defaultValue(0.3)->min(0.0)->max(100.0)->end()
                    ->end()
                ->end()
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

    /**
     * A count of the Rate Limiter component: how many in each interval.
     */
    private function limit(string $name, string $info, int $limit, string $interval): ArrayNodeDefinition
    {
        return new ArrayNodeDefinition($name)
            ->info($info)
            ->addDefaultsIfNotSet()
            ->children()
                ->integerNode('limit')->defaultValue($limit)->min(1)->end()
                ->scalarNode('interval')->info('The window of the count, in the format of the Rate Limiter component.')->defaultValue($interval)->cannotBeEmpty()->end()
            ->end();
    }
}

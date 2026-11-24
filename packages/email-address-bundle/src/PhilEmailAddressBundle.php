<?php

declare(strict_types=1);

namespace Phil\EmailAddressBundle;

use Override;
use Phil\BounceParser\Auth\AuthenticationResults;
use Phil\BounceParser\BounceParser;
use Phil\BounceParser\Limits;
use Phil\BounceParser\Rule\Rules;
use Phil\BounceParser\Verp;
use Phil\EmailAddress\Contract\MailDomainResolver;
use Phil\EmailAddress\DisposableDomains;
use Phil\EmailAddress\Dns\DnsGetRecordResolver;
use Phil\EmailAddress\DomainRules;
use Phil\EmailAddress\Exception\InvalidDomainList;
use Phil\EmailAddress\TopLevelDomains;
use Phil\EmailAddress\TypoSuggester;
use Phil\EmailAddress\Validator\DeliverableEmailAddressValidator;
use Phil\EmailAddressBundle\Application\Bounces\BounceIngestor;
use Phil\EmailAddressBundle\Application\Bounces\BounceNotices;
use Phil\EmailAddressBundle\Application\Bounces\BounceRules;
use Phil\EmailAddressBundle\Application\Bounces\MailboxPoller;
use Phil\EmailAddressBundle\Application\Contract\BounceMailbox;
use Phil\EmailAddressBundle\Application\Contract\CurrentSite;
use Phil\EmailAddressBundle\Application\EmailAddresses;
use Phil\EmailAddressBundle\Application\EmailPolicies;
use Phil\EmailAddressBundle\Application\EmailPolicy;
use Phil\EmailAddressBundle\Domain\Contract\BounceHistoryRepository;
use Phil\EmailAddressBundle\Domain\Event\BounceDecided;
use Phil\EmailAddressBundle\Infrastructure\Cache\CacheBounceHistoryRepository;
use Phil\EmailAddressBundle\Infrastructure\Console\PollMailboxCommand;
use Phil\EmailAddressBundle\Infrastructure\Console\ReadBounceCommand;
use Phil\EmailAddressBundle\Infrastructure\DependencyInjection\EmailAddressFactory;
use Phil\EmailAddressBundle\Infrastructure\Dns\CachedMailDomainResolver;
use Phil\EmailAddressBundle\Infrastructure\Form\EmailAddressType;
use Phil\EmailAddressBundle\Infrastructure\Http\EmailDomainsController;
use Phil\EmailAddressBundle\Infrastructure\Mailbox\MaildirBounceMailbox;
use Phil\EmailAddressBundle\Infrastructure\Messenger\ForwardBounceDecision;
use Phil\EmailAddressBundle\Infrastructure\RemoteEvent\MailerEventConsumer;
use Phil\EmailAddressBundle\Infrastructure\Validator\AcceptedEmailAddressValidator;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\Configurator\InlineServiceConfigurator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\RemoteEvent\Consumer\ConsumerInterface;

use function Symfony\Component\DependencyInjection\Loader\Configurator\inline_service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/**
 * `phil/email-address` and `phil/bounce-parser` in a Symfony application: the library's constraint on the container,
 * a policy per site and per list, the email field with its typo suggestion in the browser, the bounce rules and their
 * three inputs — a provider's webhook, a raw message piped by the mail server, the bounce mailbox.
 *
 * ```yaml
 * phil_email_address:
 *     check_mail_server: true
 *     lists:
 *         staff: { allowed_domains: [example.org] }
 *     suggestions:
 *         url: /email-address/domains.json
 *     bounces:
 *         remote_event_consumers: [mailer_provider]
 * ```
 */
final class PhilEmailAddressBundle extends AbstractBundle
{
    private const array POLICY_OPTIONS = [
        'check_mail_server' => 'Looks up a mail server of the domain, through the configured resolver.',
        'allow_disposable' => 'Accepts the domains that give disposable mailboxes.',
        'known_top_level_domain' => 'Refuses a domain whose top-level domain the root zone does not delegate.',
        'international_local_part' => 'Accepts international characters before the @ (RFC 6531).',
    ];

    private const array POLICY_DEFAULTS = [
        'check_mail_server' => false,
        'allow_disposable' => false,
        'known_top_level_domain' => true,
        'international_local_part' => false,
    ];

    #[Override]
    protected string $extensionAlias = 'phil_email_address';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $root = $definition->rootNode()->children();
        $this->policyNodes($root, false);

        $root
            ->arrayNode('disposable_domains')
                ->info('Disposable domains added to the list the library imports.')
                ->scalarPrototype()->end()
                ->validate()
                    ->ifTrue(static fn (array $domains): bool => !self::validDomains($domains))
                    ->thenInvalid('A disposable domain is a domain name: %s.')
                ->end()
            ->end()
            ->variableNode('role_local_parts')
                ->info("The parts before the @ that name a role, warned about and accepted; null keeps the bundle's list.")
                ->defaultNull()
                ->validate()
                    ->ifTrue(static fn (mixed $parts): bool => null !== $parts && !self::isListOfStrings($parts))
                    ->thenInvalid('The role local parts are a list of strings: %s.')
                ->end()
            ->end()
            ->scalarNode('current_site')
                ->info('The id of a service implementing CurrentSite, whose key picks the policy of "sites"; null applies none.')
                ->defaultNull()
            ->end()
            ->scalarNode('fingerprint_key')
                ->info('The secret the address fingerprints are keyed with.')
                ->defaultValue('%kernel.secret%')
                ->cannotBeEmpty()
            ->end();

        foreach (['sites' => "The policy of each site, by its key, laid over the bundle's.", 'lists' => "The policy of each list, by its name, laid over the site's."] as $name => $info) {
            $this->policyNodes($root->arrayNode($name)->info($info)->useAttributeAsKey('name')->arrayPrototype()->children(), true);
        }

        $root
            ->arrayNode('dns')
                ->addDefaultsIfNotSet()
                ->children()
                    ->scalarNode('resolver')
                        ->info('The id of a service implementing MailDomainResolver; null asks the system resolver through dns_get_record().')
                        ->defaultNull()
                    ->end()
                    ->scalarNode('cache')
                        ->info('The cache pool that keeps the answers; null keeps none.')
                        ->defaultValue('cache.app')
                    ->end()
                    ->integerNode('ttl')
                        ->info('How long an answer is kept, in seconds.')
                        ->defaultValue(3600)
                        ->min(1)
                    ->end()
                ->end()
            ->end()
            ->arrayNode('suggestions')
                ->addDefaultsIfNotSet()
                ->children()
                    ->variableNode('domains')
                        ->info("The domains suggested, in lowercase ASCII, most used first; null keeps the library's list.")
                        ->defaultNull()
                        ->validate()
                            ->ifTrue(static fn (mixed $domains): bool => null !== $domains && !self::isListOfStrings($domains))
                            ->thenInvalid('The suggested domains are a list of strings: %s.')
                        ->end()
                    ->end()
                    ->scalarNode('url')
                        ->info('The URL of the route phil_email_address_domains, which the email field gives its Stimulus controller; null leaves the field without it.')
                        ->defaultNull()
                    ->end()
                ->end()
            ->end()
            ->arrayNode('bounces')
                ->canBeDisabled()
                ->children()
                    ->integerNode('soft_bounce_threshold')
                        ->info('The soft bounces within the window that suspend an address.')
                        ->defaultValue(3)
                        ->min(1)
                    ->end()
                    ->scalarNode('soft_bounce_window')
                        ->info('The window soft bounces are counted in, an ISO 8601 duration.')
                        ->defaultValue('P30D')
                        ->validate()
                            ->ifTrue(static fn (mixed $window): bool => !\is_string($window) || 1 !== preg_match('/^P(?=\d|T\d)(\d+Y)?(\d+M)?(\d+W)?(\d+D)?(T(?=\d)(\d+H)?(\d+M)?(\d+S)?)?$/D', $window) || 1 !== preg_match('/[1-9]/', $window))
                            ->thenInvalid('The soft bounce window is a positive ISO 8601 duration such as P30D: %s.')
                        ->end()
                    ->end()
                    ->scalarNode('history')
                        ->info('The cache pool that keeps the notices decided and the soft bounces, shared by every server that receives bounces.')
                        ->defaultValue('cache.app')
                        ->cannotBeEmpty()
                    ->end()
                    ->arrayNode('authserv_ids')
                        ->info('The names your mail server writes its Authentication-Results under; with feedback_domains, they vouch for abuse reports.')
                        ->scalarPrototype()->end()
                    ->end()
                    ->arrayNode('feedback_domains')
                        ->info('The DKIM signing domains of the feedback loops you subscribed to.')
                        ->scalarPrototype()->end()
                    ->end()
                    ->scalarNode('authenticator')
                        ->info('The id of a service implementing ReportAuthenticator, in place of authserv_ids and feedback_domains.')
                        ->defaultNull()
                    ->end()
                    ->arrayNode('verp')
                        ->info('The return path the messages are sent with, whose signed tag names the recipient.')
                        ->canBeEnabled()
                        ->children()
                            ->scalarNode('local_part')->defaultValue('bounces')->cannotBeEmpty()->end()
                            ->scalarNode('domain')->defaultNull()->end()
                            ->scalarNode('separator')->defaultValue('+')->cannotBeEmpty()->end()
                            ->scalarNode('key')->defaultValue('%kernel.secret%')->cannotBeEmpty()->end()
                        ->end()
                        ->validate()
                            ->ifTrue(static fn (array $verp): bool => true === $verp['enabled'] && (!\is_string($verp['domain']) || '' === $verp['domain']))
                            ->thenInvalid('The VERP return path needs its domain: %s.')
                        ->end()
                    ->end()
                    ->arrayNode('remote_event_consumers')
                        ->info('The names of the webhooks, under framework.webhook.routing, whose mailer events the bounce rules consume.')
                        ->scalarPrototype()->end()
                    ->end()
                    ->scalarNode('recipient_metadata')
                        ->info('The key of the message metadata that holds the recipient identifier, in the events of a webhook.')
                        ->defaultNull()
                    ->end()
                    ->scalarNode('message_bus')
                        ->info('The id of a message bus each decision is sent on to as a BounceDecidedMessage, without the address; null sends none.')
                        ->defaultNull()
                    ->end()
                    ->scalarNode('mailbox')
                        ->info('The id of a service implementing BounceMailbox, which phil:email-address:poll-mailbox reads.')
                        ->defaultNull()
                    ->end()
                    ->scalarNode('maildir')
                        ->info('The Maildir the mail server delivers bounces to, in place of mailbox.')
                        ->defaultNull()
                    ->end()
                ->end()
            ->end();
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var array{dns: array{resolver: string|null, cache: string|null, ttl: positive-int}, suggestions: array{domains: list<string>|null, url: string|null}, bounces: array<string, mixed>, disposable_domains: list<string>, role_local_parts: list<string>|null, current_site: string|null, fingerprint_key: string, sites: array<string, array<string, mixed>>, lists: array<string, array<string, mixed>>} $config */
        $services = $container->services();

        $services->set(EmailAddresses::class)->args([$config['fingerprint_key'], $config['role_local_parts'] ?? EmailAddresses::ROLE_LOCAL_PARTS]);
        $services->set('phil_email_address.disposable_domains', DisposableDomains::class)
            ->factory(EmailAddressFactory::disposableDomains(...))
            ->args([$config['disposable_domains']]);
        $services->set('phil_email_address.top_level_domains', TopLevelDomains::class)->factory([TopLevelDomains::class, 'bundled']);
        $services->set(TypoSuggester::class)->args(null === $config['suggestions']['domains'] ? [] : [$config['suggestions']['domains']]);

        $this->loadResolver($config['dns'], $services);

        $services->set(DeliverableEmailAddressValidator::class)
            ->args([service('phil_email_address.disposable_domains'), service('phil_email_address.top_level_domains'), service(MailDomainResolver::class), service(TypoSuggester::class)])
            ->tag('validator.constraint_validator');

        if (null !== $config['current_site']) {
            $services->alias(CurrentSite::class, $config['current_site']);
        }

        $services->set(EmailPolicies::class)->args([
            $this->policyService($config),
            array_map($this->policyService(...), $config['sites']),
            array_map($this->policyService(...), $config['lists']),
            null === $config['current_site'] ? null : service(CurrentSite::class),
        ]);
        $services->set(AcceptedEmailAddressValidator::class)->args([service(EmailPolicies::class)])->tag('validator.constraint_validator');
        $services->set(EmailDomainsController::class)
            ->args([service(EmailAddresses::class), $config['suggestions']['domains'] ?? TypoSuggester::DOMAINS, TypoSuggester::TOP_LEVEL_SLIPS])
            ->tag('controller.service_arguments')
            ->public();

        if (class_exists(AbstractType::class)) {
            $services->set(EmailAddressType::class)
                ->args([service(EmailAddresses::class), service(TypoSuggester::class), $config['suggestions']['url'], new Reference('translator', ContainerInterface::NULL_ON_INVALID_REFERENCE)])
                ->tag('form.type');
        }

        if (true === $config['bounces']['enabled']) {
            $this->loadBounces($config['bounces'], $services);
        }
    }

    /**
     * @param array{resolver: string|null, cache: string|null, ttl: positive-int} $dns
     */
    private function loadResolver(array $dns, ServicesConfigurator $services): void
    {
        $inner = null === $dns['resolver'] ? inline_service(DnsGetRecordResolver::class) : service($dns['resolver']);

        if (null !== $dns['cache']) {
            $services->set(MailDomainResolver::class, CachedMailDomainResolver::class)->args([$inner, service($dns['cache']), $dns['ttl']]);
        } elseif (null !== $dns['resolver']) {
            $services->alias(MailDomainResolver::class, $dns['resolver']);
        } else {
            $services->set(MailDomainResolver::class, DnsGetRecordResolver::class);
        }
    }

    /**
     * @param array<string, mixed> $bounces
     */
    private function loadBounces(array $bounces, ServicesConfigurator $services): void
    {
        /** @var array{soft_bounce_threshold: positive-int, soft_bounce_window: string, history: string, authserv_ids: list<string>, feedback_domains: list<string>, authenticator: string|null, verp: array{enabled: bool, local_part: string, domain: string|null, separator: string, key: string}, remote_event_consumers: list<string>, recipient_metadata: string|null, message_bus: string|null, mailbox: string|null, maildir: string|null} $bounces */
        $authenticator = match (true) {
            null !== $bounces['authenticator'] => service($bounces['authenticator']),
            [] !== $bounces['authserv_ids'] && [] !== $bounces['feedback_domains'] => inline_service(AuthenticationResults::class)->args([$bounces['authserv_ids'], $bounces['feedback_domains']]),
            default => null,
        };
        $verp = $bounces['verp']['enabled'] ? inline_service(Verp::class)->args([$bounces['verp']['local_part'], $bounces['verp']['domain'], $bounces['verp']['separator']]) : null;
        $clock = service('clock')->nullOnInvalid();

        $services->set(Limits::class);
        $services->set(BounceParser::class)->args([inline_service(Rules::class), $verp, $authenticator, service(Limits::class)]);
        $services->set(BounceHistoryRepository::class, CacheBounceHistoryRepository::class)->args([service($bounces['history'])]);
        $services->set(BounceNotices::class)->args([service(EmailAddresses::class), $verp, $bounces['verp']['enabled'] ? $bounces['verp']['key'] : '', $clock]);
        $services->set(BounceRules::class)->args([
            service(BounceHistoryRepository::class),
            service('event_dispatcher'),
            $clock,
            $bounces['soft_bounce_threshold'],
            $bounces['soft_bounce_window'],
        ]);
        $services->set(BounceIngestor::class)->args([service(BounceParser::class), service(BounceNotices::class), service(BounceRules::class)]);

        if (class_exists(Command::class)) {
            $services->set(ReadBounceCommand::class)->args([service(BounceIngestor::class), service(Limits::class)])->tag('console.command');
        }

        if (null !== $bounces['mailbox']) {
            $services->alias(BounceMailbox::class, $bounces['mailbox']);
        } elseif (null !== $bounces['maildir']) {
            $services->set(BounceMailbox::class, MaildirBounceMailbox::class)->args([$bounces['maildir']]);
        }

        if (null !== $bounces['mailbox'] || null !== $bounces['maildir']) {
            $services->set(MailboxPoller::class)->args([service(BounceMailbox::class), service(BounceIngestor::class)]);

            if (class_exists(Command::class)) {
                $services->set(PollMailboxCommand::class)->args([service(MailboxPoller::class)])->tag('console.command');
            }
        }

        if ([] !== $bounces['remote_event_consumers'] && interface_exists(ConsumerInterface::class)) {
            $consumer = $services->set(MailerEventConsumer::class)->args([service(BounceNotices::class), service(BounceRules::class), $bounces['recipient_metadata']]);

            foreach ($bounces['remote_event_consumers'] as $name) {
                $consumer->tag('remote_event.consumer', ['consumer' => $name]);
            }
        }

        if (null !== $bounces['message_bus'] && interface_exists(MessageBusInterface::class)) {
            $services->set(ForwardBounceDecision::class)->args([service($bounces['message_bus'])])->tag('kernel.event_listener', ['event' => BounceDecided::class]);
        }
    }

    /**
     * The options of a policy: those of the bundle with their defaults, or those of a site or a list, which leave
     * each choice to the policy under them.
     */
    private function policyNodes(NodeBuilder $nodes, bool $override): void
    {
        foreach (self::POLICY_OPTIONS as $name => $info) {
            $node = $nodes->booleanNode($name)->info($info);
            $override ? $node->defaultNull() : $node->defaultValue(self::POLICY_DEFAULTS[$name]);
        }

        $nodes
            ->arrayNode('denied_domains')
                ->info('Domains refused, with their subdomains; a site or a list adds to them.')
                ->scalarPrototype()->end()
                ->validate()
                    ->ifTrue(static fn (array $domains): bool => !self::validDomains($domains))
                    ->thenInvalid('A denied domain is a domain name: %s.')
                ->end()
            ->end()
            ->variableNode('allowed_domains')
                ->info($override ? 'The only domains accepted, with their subdomains; null keeps those of the policy under it, an empty list accepts every domain.' : 'The only domains accepted, with their subdomains; an empty list accepts every domain.')
                ->defaultValue($override ? null : [])
                ->validate()
                    ->ifTrue(static fn (mixed $domains): bool => (null !== $domains || !$override) && (!self::isListOfStrings($domains) || !self::validDomains($domains)))
                    ->thenInvalid('The allowed domains are a list of domain names: %s.')
                ->end()
            ->end();
    }

    /**
     * @param array<string, mixed> $config a policy's options, as the tree above processed them
     */
    private function policyService(array $config): InlineServiceConfigurator
    {
        return inline_service(EmailPolicy::class)->args(array_values([
            ...array_map(static fn (string $name): mixed => $config[$name] ?? null, array_keys(self::POLICY_OPTIONS)),
            'denied_domains' => $config['denied_domains'] ?? [],
            'allowed_domains' => $config['allowed_domains'] ?? null,
        ]));
    }

    /**
     * @param array<array-key, mixed> $domains
     */
    private static function validDomains(array $domains): bool
    {
        if (!self::isListOfStrings($domains)) {
            return false;
        }

        try {
            DomainRules::of($domains);
        } catch (InvalidDomainList) {
            return false;
        }

        return true;
    }

    /**
     * @phpstan-assert-if-true list<string> $value
     */
    private static function isListOfStrings(mixed $value): bool
    {
        return \is_array($value) && array_is_list($value) && array_all($value, static fn (mixed $item): bool => \is_string($item));
    }
}

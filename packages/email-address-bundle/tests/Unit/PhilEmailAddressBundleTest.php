<?php

declare(strict_types=1);

namespace Phil\EmailAddressBundle\Tests\Unit;

use Phil\BounceParser\BounceParser;
use Phil\BounceParser\Limits;
use Phil\EmailAddress\Contract\MailDomainResolver;
use Phil\EmailAddress\DisposableDomains;
use Phil\EmailAddress\Dns\DnsGetRecordResolver;
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
use Phil\EmailAddressBundle\Domain\Contract\BounceHistoryRepository;
use Phil\EmailAddressBundle\Domain\Event\BounceDecided;
use Phil\EmailAddressBundle\Infrastructure\Cache\CacheBounceHistoryRepository;
use Phil\EmailAddressBundle\Infrastructure\Console\PollMailboxCommand;
use Phil\EmailAddressBundle\Infrastructure\Console\ReadBounceCommand;
use Phil\EmailAddressBundle\Infrastructure\Dns\CachedMailDomainResolver;
use Phil\EmailAddressBundle\Infrastructure\Form\EmailAddressType;
use Phil\EmailAddressBundle\Infrastructure\Http\EmailDomainsController;
use Phil\EmailAddressBundle\Infrastructure\Mailbox\MaildirBounceMailbox;
use Phil\EmailAddressBundle\Infrastructure\Messenger\ForwardBounceDecision;
use Phil\EmailAddressBundle\Infrastructure\RemoteEvent\MailerEventConsumer;
use Phil\EmailAddressBundle\Infrastructure\Validator\AcceptedEmailAddressValidator;
use Phil\EmailAddressBundle\PhilEmailAddressBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Config\Definition\ArrayNode;
use Symfony\Component\Config\Definition\BaseNode;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\NodeInterface;
use Symfony\Component\Config\Definition\PrototypedArrayNode;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\ConfigurationExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\MessageBus;

#[CoversClass(PhilEmailAddressBundle::class)]
final class PhilEmailAddressBundleTest extends TestCase
{
    public function testTheDefaultsRegisterTheValidatorsTheFieldAndTheRules(): void
    {
        $container = $this->load([]);

        self::assertTrue($container->getDefinition(DeliverableEmailAddressValidator::class)->hasTag('validator.constraint_validator'));
        self::assertTrue($container->getDefinition(AcceptedEmailAddressValidator::class)->hasTag('validator.constraint_validator'));
        self::assertTrue($container->getDefinition(EmailAddressType::class)->hasTag('form.type'));
        self::assertSame(CachedMailDomainResolver::class, $container->getDefinition(MailDomainResolver::class)->getClass());
        self::assertSame(['%kernel.secret%', EmailAddresses::ROLE_LOCAL_PARTS], $container->getDefinition(EmailAddresses::class)->getArguments());
        self::assertSame([3, 'P30D'], \array_slice($container->getDefinition(BounceRules::class)->getArguments(), 3));
        self::assertTrue($container->getDefinition(ReadBounceCommand::class)->hasTag('console.command'));
        self::assertFalse($container->hasDefinition(MailboxPoller::class));
        self::assertFalse($container->hasDefinition(PollMailboxCommand::class));
        self::assertFalse($container->hasDefinition(MailerEventConsumer::class));
        self::assertFalse($container->hasDefinition(ForwardBounceDecision::class));
        self::assertFalse($container->hasAlias(CurrentSite::class));
        self::assertNull($container->getDefinition(EmailPolicies::class)->getArgument(3));
        self::assertSame([null, null], \array_slice($container->getDefinition(BounceParser::class)->getArguments(), 1, 2));
    }

    public function testTheBouncesCanBeDisabled(): void
    {
        $container = $this->load([['bounces' => false]]);

        self::assertFalse($container->hasDefinition(BounceRules::class));
        self::assertFalse($container->hasDefinition(ReadBounceCommand::class));
        self::assertTrue($container->hasDefinition(AcceptedEmailAddressValidator::class));
    }

    public function testTheResolverIsTheSystemsWithoutCacheOrTheApplicationsService(): void
    {
        self::assertSame(DnsGetRecordResolver::class, $this->load([['dns' => ['cache' => null]]])->getDefinition(MailDomainResolver::class)->getClass());
        self::assertSame('app.resolver', (string) $this->load([['dns' => ['cache' => null, 'resolver' => 'app.resolver']]])->getAlias(MailDomainResolver::class));

        $cached = $this->load([['dns' => ['cache' => 'cache.dns', 'resolver' => 'app.resolver', 'ttl' => 60]]])->getDefinition(MailDomainResolver::class);
        self::assertEquals([new Reference('app.resolver'), new Reference('cache.dns'), 60], $cached->getArguments());
    }

    public function testTheSitesAndListsAndTheCurrentSiteReachThePolicies(): void
    {
        $container = $this->load([[
            'denied_domains' => ['spam.example'],
            'current_site' => 'app.site',
            'sites' => ['shop' => ['check_mail_server' => true]],
            'lists' => ['staff' => ['allowed_domains' => ['example.org']]],
        ]]);
        $arguments = $container->getDefinition(EmailPolicies::class)->getArguments();

        self::assertSame('app.site', (string) $container->getAlias(CurrentSite::class));
        self::assertEquals(new Reference(CurrentSite::class), $arguments[3]);
        self::assertInstanceOf(Definition::class, $arguments[0]);
        self::assertSame([false, false, true, false, ['spam.example'], []], $arguments[0]->getArguments());
        self::assertIsArray($arguments[1]);
        self::assertInstanceOf(Definition::class, $arguments[1]['shop']);
        self::assertSame([true, null, null, null, [], null], $arguments[1]['shop']->getArguments());
        self::assertIsArray($arguments[2]);
        self::assertInstanceOf(Definition::class, $arguments[2]['staff']);
        self::assertSame([null, null, null, null, [], ['example.org']], $arguments[2]['staff']->getArguments());
    }

    public function testTheSuggestionsAndRolesAreConfigured(): void
    {
        $container = $this->load([['suggestions' => ['domains' => ['example.org'], 'url' => '/domains.json'], 'role_local_parts' => ['desk']]]);

        self::assertSame([['example.org']], $container->getDefinition(TypoSuggester::class)->getArguments());
        self::assertSame('/domains.json', $container->getDefinition(EmailAddressType::class)->getArgument(2));
        self::assertSame(['%kernel.secret%', ['desk']], $container->getDefinition(EmailAddresses::class)->getArguments());
        self::assertSame(['example.org'], $container->getDefinition(EmailDomainsController::class)->getArgument(1));
    }

    public function testTheBounceInputsAreRegisteredWhenConfigured(): void
    {
        $container = $this->load([['bounces' => [
            'remote_event_consumers' => ['provider_a', 'provider_b'],
            'recipient_metadata' => 'recipient',
            'message_bus' => 'messenger.default_bus',
            'maildir' => '/var/mail/bounces',
            'authserv_ids' => ['mx.example.net'],
            'feedback_domains' => ['fbl.example.com'],
            'verp' => ['domain' => 'example.net'],
            'soft_bounce_threshold' => 5,
            'soft_bounce_window' => 'P7D',
        ]]]);

        self::assertSame([['consumer' => 'provider_a'], ['consumer' => 'provider_b']], $container->getDefinition(MailerEventConsumer::class)->getTag('remote_event.consumer'));
        self::assertSame('recipient', $container->getDefinition(MailerEventConsumer::class)->getArgument(2));
        self::assertSame([['event' => BounceDecided::class]], $container->getDefinition(ForwardBounceDecision::class)->getTag('kernel.event_listener'));
        self::assertSame(MaildirBounceMailbox::class, $container->getDefinition(BounceMailbox::class)->getClass());
        self::assertSame(['/var/mail/bounces'], $container->getDefinition(BounceMailbox::class)->getArguments());
        self::assertTrue($container->getDefinition(PollMailboxCommand::class)->hasTag('console.command'));
        self::assertSame([5, 'P7D'], \array_slice($container->getDefinition(BounceRules::class)->getArguments(), 3));

        $parser = $container->getDefinition(BounceParser::class)->getArguments();
        self::assertInstanceOf(Definition::class, $parser[1]);
        self::assertSame(['bounces', 'example.net', '+'], $parser[1]->getArguments());
        self::assertInstanceOf(Definition::class, $parser[2]);
        self::assertSame([['mx.example.net'], ['fbl.example.com']], $parser[2]->getArguments());
        self::assertSame('%kernel.secret%', $container->getDefinition(BounceNotices::class)->getArgument(2));
    }

    public function testAnAuthenticatorServiceAndAMailboxServiceAreUsed(): void
    {
        $container = $this->load([['bounces' => ['authenticator' => 'app.authenticator', 'mailbox' => 'app.mailbox', 'authserv_ids' => ['mx.example.net'], 'feedback_domains' => ['fbl.example.com']]]]);

        self::assertEquals(new Reference('app.authenticator'), $container->getDefinition(BounceParser::class)->getArgument(2));
        self::assertSame('app.mailbox', (string) $container->getAlias(BounceMailbox::class));
        self::assertTrue($container->hasDefinition(MailboxPoller::class));
        self::assertSame('', $container->getDefinition(BounceNotices::class)->getArgument(2));
    }

    public function testAuthservIdsWithoutFeedbackDomainsVouchForNothing(): void
    {
        self::assertNull($this->load([['bounces' => ['authserv_ids' => ['mx.example.net']]]])->getDefinition(BounceParser::class)->getArgument(2));
        self::assertNull($this->load([['bounces' => ['feedback_domains' => ['fbl.example.com']]]])->getDefinition(BounceParser::class)->getArgument(2));
    }

    /**
     * @param array<string, mixed> $config
     */
    #[TestWith([['denied_domains' => ['not a domain']], 'A denied domain is a domain name'])]
    #[TestWith([['allowed_domains' => ['not a domain']], 'The allowed domains are a list of domain names'])]
    #[TestWith([['allowed_domains' => null], 'The allowed domains are a list of domain names'])]
    #[TestWith([['allowed_domains' => 'example.org'], 'The allowed domains are a list of domain names'])]
    #[TestWith([['lists' => ['staff' => ['allowed_domains' => [3]]]], 'The allowed domains are a list of domain names'])]
    #[TestWith([['disposable_domains' => ['not a domain']], 'A disposable domain is a domain name'])]
    #[TestWith([['role_local_parts' => 'info'], 'The role local parts are a list of strings'])]
    #[TestWith([['role_local_parts' => ['a' => 'info']], 'The role local parts are a list of strings'])]
    #[TestWith([['role_local_parts' => [3]], 'The role local parts are a list of strings'])]
    #[TestWith([['suggestions' => ['domains' => 'gmail.com']], 'The suggested domains are a list of strings'])]
    #[TestWith([['bounces' => ['soft_bounce_window' => '30 days']], 'The soft bounce window is a positive ISO 8601 duration'])]
    #[TestWith([['bounces' => ['soft_bounce_window' => 'P0D']], 'The soft bounce window is a positive ISO 8601 duration'])]
    #[TestWith([['bounces' => ['soft_bounce_window' => 'P']], 'The soft bounce window is a positive ISO 8601 duration'])]
    #[TestWith([['bounces' => ['soft_bounce_window' => 'PT']], 'The soft bounce window is a positive ISO 8601 duration'])]
    #[TestWith([['bounces' => ['soft_bounce_window' => "P30D\n"]], 'The soft bounce window is a positive ISO 8601 duration'])]
    #[TestWith([['bounces' => ['verp' => true]], 'The VERP return path needs its domain'])]
    #[TestWith([['bounces' => ['verp' => ['domain' => '']]], 'The VERP return path needs its domain'])]
    public function testAWrongConfigurationIsRefused(array $config, string $message): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains($message);

        $this->load([$config]);
    }

    #[TestWith(['P1Y2M3W4DT5H6M7S'])]
    #[TestWith(['PT12H'])]
    #[TestWith(['P10D'])]
    public function testAWindowIsAnIsoDuration(string $window): void
    {
        self::assertSame($window, $this->load([['bounces' => ['soft_bounce_window' => $window]]])->getDefinition(BounceRules::class)->getArgument(4));
    }

    public function testEveryServiceIsBuilt(): void
    {
        $container = $this->load([[
            'sites' => ['shop' => ['check_mail_server' => true]],
            'suggestions' => ['url' => '/domains.json'],
            'bounces' => [
                'remote_event_consumers' => ['provider_a'],
                'message_bus' => 'app.bus',
                'maildir' => '/var/mail/bounces',
            ],
        ]]);
        $container->setParameter('kernel.secret', 'secret');
        $container->register('cache.app', ArrayAdapter::class);
        $container->register('event_dispatcher', EventDispatcher::class);
        $container->register('app.bus', MessageBus::class);

        $ids = array_keys($container->getDefinitions());

        foreach ($container->getDefinitions() as $definition) {
            $definition->setPublic(true);
        }

        $container->compile(true);

        $built = [];

        foreach ($ids as $id) {
            $built[$id] = $container->get($id)::class;
        }

        self::assertSame(DisposableDomains::class, $built['phil_email_address.disposable_domains']);
        self::assertSame(TopLevelDomains::class, $built['phil_email_address.top_level_domains']);
        self::assertSame(CacheBounceHistoryRepository::class, $built[BounceHistoryRepository::class]);
        self::assertSame(MaildirBounceMailbox::class, $built[BounceMailbox::class]);

        foreach ([DeliverableEmailAddressValidator::class, AcceptedEmailAddressValidator::class, EmailDomainsController::class, EmailAddressType::class, Limits::class, BounceParser::class, BounceNotices::class, BounceRules::class, BounceIngestor::class, ReadBounceCommand::class, MailboxPoller::class, PollMailboxCommand::class, MailerEventConsumer::class, ForwardBounceDecision::class] as $class) {
            self::assertSame($class, $built[$class]);
        }
    }

    public function testTheDnsAnswersAreKeptAnHourByDefault(): void
    {
        self::assertSame(3600, $this->load([])->getDefinition(MailDomainResolver::class)->getArgument(2));
        self::assertSame(1, $this->load([['dns' => ['ttl' => 1]]])->getDefinition(MailDomainResolver::class)->getArgument(2));
    }

    public function testOneSoftBounceCanSuspend(): void
    {
        self::assertSame(1, $this->load([['bounces' => ['soft_bounce_threshold' => 1]]])->getDefinition(BounceRules::class)->getArgument(3));
    }

    /**
     * @param array<string, mixed> $config
     */
    #[TestWith([['dns' => ['ttl' => 0]]])]
    #[TestWith([['bounces' => ['soft_bounce_threshold' => 0]]])]
    #[TestWith([['bounces' => ['soft_bounce_window' => 'XP30D']]])]
    public function testAZeroOrAWindowWithAPrefixIsRefused(array $config): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->load([$config]);
    }

    public function testTheAllowedDomainsOfASiteOrAListKeepThePolicyUnderThem(): void
    {
        $extension = $this->extension();
        self::assertInstanceOf(ConfigurationExtensionInterface::class, $extension);
        $configuration = $extension->getConfiguration([], new ContainerBuilder());
        self::assertInstanceOf(ConfigurationInterface::class, $configuration);
        $tree = $configuration->getConfigTreeBuilder()->buildTree();
        self::assertInstanceOf(ArrayNode::class, $tree);
        $root = $tree->getChildren();

        self::assertSame('The only domains accepted, with their subdomains; an empty list accepts every domain.', $this->info($root['allowed_domains']));

        foreach (['sites', 'lists'] as $name) {
            $node = $root[$name];
            self::assertInstanceOf(PrototypedArrayNode::class, $node);
            $prototype = $node->getPrototype();
            self::assertInstanceOf(ArrayNode::class, $prototype);

            self::assertSame(
                'The only domains accepted, with their subdomains; null keeps those of the policy under it, an empty list accepts every domain.',
                $this->info($prototype->getChildren()['allowed_domains']),
            );
        }
    }

    public function testTheAlias(): void
    {
        self::assertSame('phil_email_address', $this->extension()->getAlias());
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

    private function info(NodeInterface $node): ?string
    {
        self::assertInstanceOf(BaseNode::class, $node);

        return $node->getInfo();
    }

    private function extension(): ExtensionInterface
    {
        $extension = new PhilEmailAddressBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        return $extension;
    }
}

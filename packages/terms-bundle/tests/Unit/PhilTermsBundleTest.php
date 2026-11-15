<?php

declare(strict_types=1);

namespace Phil\TermsBundle\Tests\Unit;

use Phil\IpAnonymizer\Truncation;
use Phil\LegalNoticesBundle\Infrastructure\Twig\FooterLinks;
use Phil\NotificationsBundle\Application\Notifications;
use Phil\TermsBundle\Application\AcceptanceChecker;
use Phil\TermsBundle\Application\Contract\AddressMasker;
use Phil\TermsBundle\Application\Contract\MemberResolver;
use Phil\TermsBundle\Application\Contract\MemberSource;
use Phil\TermsBundle\Application\Contract\PolicyTexts;
use Phil\TermsBundle\Application\Contract\TermsNotifier;
use Phil\TermsBundle\Application\ContributionLicences;
use Phil\TermsBundle\Application\Handler\SendTermsRemindersHandler;
use Phil\TermsBundle\Application\Message\SendTermsReminders;
use Phil\TermsBundle\Application\Outstanding;
use Phil\TermsBundle\Application\Policies;
use Phil\TermsBundle\Application\Terms;
use Phil\TermsBundle\Application\VersionCatalog;
use Phil\TermsBundle\Domain\Contract\AcceptanceRepository;
use Phil\TermsBundle\Domain\Contract\PolicyVersionRepository;
use Phil\TermsBundle\Domain\ValueObject\Policy;
use Phil\TermsBundle\Infrastructure\Console\LegalLinksCommand;
use Phil\TermsBundle\Infrastructure\Console\OutstandingCommand;
use Phil\TermsBundle\Infrastructure\DependencyInjection\KitSubdirectoryPass;
use Phil\TermsBundle\Infrastructure\DependencyInjection\TermsServices;
use Phil\TermsBundle\Infrastructure\Doctrine\DbalAcceptances;
use Phil\TermsBundle\Infrastructure\Doctrine\DbalPolicyVersions;
use Phil\TermsBundle\Infrastructure\Doctrine\TermsSchemaListener;
use Phil\TermsBundle\Infrastructure\Form\SignupAcceptances;
use Phil\TermsBundle\Infrastructure\Form\TermsAcceptanceTypeExtension;
use Phil\TermsBundle\Infrastructure\Http\AcceptanceController;
use Phil\TermsBundle\Infrastructure\Http\AcceptanceGate;
use Phil\TermsBundle\Infrastructure\Http\Pages;
use Phil\TermsBundle\Infrastructure\Http\PolicyController;
use Phil\TermsBundle\Infrastructure\Http\TermsAdminController;
use Phil\TermsBundle\Infrastructure\IpAnonymizer\NetworkMasker;
use Phil\TermsBundle\Infrastructure\Notifications\NotificationsNotifier;
use Phil\TermsBundle\Infrastructure\Notifications\OutstandingAudience;
use Phil\TermsBundle\Infrastructure\Privacy\AcceptancesPrivacy;
use Phil\TermsBundle\Infrastructure\Scheduler\TermsSchedule;
use Phil\TermsBundle\Infrastructure\Security\TokenMemberResolver;
use Phil\TermsBundle\Infrastructure\Text\PolicyTextRenderer;
use Phil\TermsBundle\Infrastructure\Twig\KitComponents;
use Phil\TermsBundle\Infrastructure\Twig\TermsTwig;
use Phil\TermsBundle\PhilTermsBundle;
use Phil\TextDiff\TextDiff;
use Phil\Ujo\Ujo;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(PhilTermsBundle::class)]
#[CoversClass(TermsServices::class)]
#[CoversClass(KitSubdirectoryPass::class)]
#[CoversClass(KitComponents::class)]
#[CoversClass(TermsTwig::class)]
final class PhilTermsBundleTest extends TestCase
{
    public function testTheDefaults(): void
    {
        $container = $this->load([]);

        self::assertSame('phil_terms', $this->extension()->getAlias());
        self::assertSame([
            'service_container',
            Policies::class,
            DbalPolicyVersions::class,
            DbalAcceptances::class,
            TermsSchemaListener::class,
            VersionCatalog::class,
            PolicyTextRenderer::class,
            TokenMemberResolver::class,
            AcceptanceChecker::class,
            Terms::class,
            ContributionLicences::class,
            Outstanding::class,
            KitComponents::class,
            TermsTwig::class,
            Pages::class,
            AcceptanceController::class,
            PolicyController::class,
            AcceptanceGate::class,
            TermsAcceptanceTypeExtension::class,
            SignupAcceptances::class,
            OutstandingCommand::class,
            AcceptancesPrivacy::class,
        ], array_keys($container->getDefinitions()));

        $connection = new Reference('doctrine.dbal.default_connection');
        $clock = new Reference('clock');
        self::assertSame([], $container->getDefinition(Policies::class)->getArguments());
        self::assertSame([null], $container->getDefinition(KitComponents::class)->getArguments());
        self::assertEquals([new Reference(KitComponents::class)], $container->getDefinition(TermsTwig::class)->getArguments());
        self::assertSame(['twig.attribute_extension' => [[]], 'twig.runtime' => [[]]], $container->getDefinition(TermsTwig::class)->getTags());
        self::assertEquals([$connection], $container->getDefinition(DbalPolicyVersions::class)->getArguments());
        self::assertEquals([$connection], $container->getDefinition(DbalAcceptances::class)->getArguments());
        self::assertSame(DbalPolicyVersions::class, (string) $container->getAlias(PolicyVersionRepository::class));
        self::assertTrue($container->getAlias(PolicyVersionRepository::class)->isPublic());
        self::assertSame(DbalAcceptances::class, (string) $container->getAlias(AcceptanceRepository::class));
        self::assertTrue($container->getAlias(AcceptanceRepository::class)->isPublic());
        self::assertSame(['doctrine.event_listener' => [['event' => 'postGenerateSchema', 'connection' => 'default']]], $container->getDefinition(TermsSchemaListener::class)->getTags());
        self::assertEquals([$connection], $container->getDefinition(TermsSchemaListener::class)->getArguments());

        $catalog = $container->getDefinition(VersionCatalog::class);
        self::assertEquals([new Reference(PolicyVersionRepository::class)], $catalog->getArguments());
        self::assertSame(['kernel.reset' => [['method' => 'reset']]], $catalog->getTags());
        self::assertTrue($catalog->isPublic());
        self::assertEquals([new Definition(Ujo::class), new Definition(TextDiff::class)], $container->getDefinition(PolicyTextRenderer::class)->getArguments());
        self::assertSame(PolicyTextRenderer::class, (string) $container->getAlias(PolicyTexts::class));

        self::assertEquals([new Reference('security.token_storage')], $container->getDefinition(TokenMemberResolver::class)->getArguments());
        self::assertSame(TokenMemberResolver::class, (string) $container->getAlias(MemberResolver::class));
        self::assertSame('app.members', (string) $container->getAlias(MemberSource::class));
        self::assertFalse($container->hasAlias(TermsNotifier::class));
        self::assertFalse($container->hasAlias(AddressMasker::class));

        self::assertEquals([new Reference(Policies::class), new Reference(VersionCatalog::class), new Reference(AcceptanceRepository::class)], $container->getDefinition(AcceptanceChecker::class)->getArguments());
        self::assertTrue($container->getDefinition(AcceptanceChecker::class)->isPublic());
        self::assertEquals([
            new Reference(Policies::class),
            new Reference(VersionCatalog::class),
            new Reference(PolicyVersionRepository::class),
            new Reference(PolicyTexts::class),
            new Reference(AcceptanceRepository::class),
            $clock,
            null,
            null,
            new Reference('event_dispatcher', ContainerInterface::NULL_ON_INVALID_REFERENCE),
        ], $container->getDefinition(Terms::class)->getArguments());
        self::assertTrue($container->getDefinition(Terms::class)->isPublic());
        self::assertEquals([new Reference(Policies::class), new Reference(VersionCatalog::class), new Reference(AcceptanceRepository::class), new Reference(MemberSource::class)], $container->getDefinition(Outstanding::class)->getArguments());
        self::assertTrue($container->getDefinition(Outstanding::class)->isPublic());
        self::assertEquals([new Reference(Policies::class), new Reference(VersionCatalog::class), new Reference(AcceptanceRepository::class)], $container->getDefinition(ContributionLicences::class)->getArguments());
        self::assertTrue($container->getDefinition(ContributionLicences::class)->isPublic());

        self::assertEquals([new Reference('twig'), new Reference('security.csrf.token_manager'), new Reference('router'), PhilTermsBundle::LAYOUT], $container->getDefinition(Pages::class)->getArguments());
        $acceptance = $container->getDefinition(AcceptanceController::class);
        self::assertEquals([new Reference(Pages::class), new Reference(AcceptanceChecker::class), new Reference(Terms::class), new Reference(MemberResolver::class), new Reference(PolicyTexts::class), $clock, 'en'], $acceptance->getArguments());
        self::assertTrue($acceptance->isPublic());
        self::assertSame(['controller.service_arguments' => [[]]], $acceptance->getTags());
        $policy = $container->getDefinition(PolicyController::class);
        self::assertEquals([new Reference(Pages::class), new Reference(Policies::class), new Reference(VersionCatalog::class), new Reference(PolicyTexts::class), $clock, 'en'], $policy->getArguments());
        self::assertTrue($policy->isPublic());
        self::assertSame(['controller.service_arguments' => [[]]], $policy->getTags());

        $gate = $container->getDefinition(AcceptanceGate::class);
        self::assertEquals([
            new Reference(AcceptanceChecker::class),
            new Reference(MemberResolver::class),
            $clock,
            new Reference('router'),
            [],
            ['/_wdt', '/_profiler', '/_error'],
            [],
            'about:blank',
            new Reference('translator', ContainerInterface::NULL_ON_INVALID_REFERENCE),
        ], $gate->getArguments());
        self::assertSame(['kernel.event_listener' => [['event' => 'kernel.request', 'method' => 'onKernelRequest', 'priority' => 7]]], $gate->getTags());

        $extension = $container->getDefinition(TermsAcceptanceTypeExtension::class);
        self::assertEquals([new Reference(Policies::class), new Reference(VersionCatalog::class), $clock, new Reference('translator', ContainerInterface::NULL_ON_INVALID_REFERENCE)], $extension->getArguments());
        self::assertSame(['form.type_extension' => [[]]], $extension->getTags());
        self::assertEquals([new Reference(Terms::class)], $container->getDefinition(SignupAcceptances::class)->getArguments());
        self::assertTrue($container->getDefinition(SignupAcceptances::class)->isPublic());
        self::assertEquals([new Reference(Outstanding::class), $clock], $container->getDefinition(OutstandingCommand::class)->getArguments());
        self::assertSame(['console.command' => [[]]], $container->getDefinition(OutstandingCommand::class)->getTags());
        self::assertEquals([new Reference(AcceptanceRepository::class)], $container->getDefinition(AcceptancesPrivacy::class)->getArguments());
        self::assertSame([TermsServices::EXPORTER_TAG => [[]], TermsServices::ERASER_TAG => [[]]], $container->getDefinition(AcceptancesPrivacy::class)->getTags());
    }

    public function testEverythingEnabled(): void
    {
        $container = $this->load([
            'connection' => 'legal',
            'clock' => 'app.clock',
            'default_locale' => 'fr',
            'locales' => ['en', 'fr', 'es'],
            'timezone' => 'America/Toronto',
            'policies' => [
                'terms' => [],
                'privacy' => ['renew_after_days' => 365],
                'code-of-conduct' => ['required' => false, 'contribution_licence' => true],
            ],
            'member_resolver' => 'app.member',
            'member_source' => 'app.members',
            'gate' => ['exempt_routes' => ['logout'], 'exempt_paths' => ['/media/'], 'api_paths' => ['/api/'], 'problem_type' => 'https://example.org/terms'],
            'network' => ['ipv4_prefix' => 16, 'ipv6_prefix' => 32],
            'pages' => ['layout' => 'site.html.twig'],
            'admin' => ['role' => 'ROLE_LEGAL', 'layout' => 'admin.html.twig'],
            'notifications' => ['published_type' => 'legal.published', 'reminder_type' => 'legal.reminder'],
            'reminders' => ['frequency' => '1 day', 'days_ahead' => 7],
        ]);

        $connection = new Reference('doctrine.dbal.legal_connection');
        $clock = new Reference('app.clock');
        self::assertEquals([
            new Definition(Policy::class, ['terms', true, null, false]),
            new Definition(Policy::class, ['privacy', true, 365, false]),
            new Definition(Policy::class, ['code-of-conduct', false, null, true]),
        ], $container->getDefinition(Policies::class)->getArguments());
        self::assertEquals([$connection], $container->getDefinition(DbalPolicyVersions::class)->getArguments());
        self::assertSame(['doctrine.event_listener' => [['event' => 'postGenerateSchema', 'connection' => 'legal']]], $container->getDefinition(TermsSchemaListener::class)->getTags());

        self::assertFalse($container->hasDefinition(TokenMemberResolver::class));
        self::assertSame('app.member', (string) $container->getAlias(MemberResolver::class));
        self::assertSame('app.members', (string) $container->getAlias(MemberSource::class));

        self::assertEquals([new Definition(Truncation::class, [16, 32])], $container->getDefinition(NetworkMasker::class)->getArguments());
        self::assertSame(NetworkMasker::class, (string) $container->getAlias(AddressMasker::class));
        self::assertEquals([new Reference(Notifications::class), 'legal.published', 'legal.reminder'], $container->getDefinition(NotificationsNotifier::class)->getArguments());
        self::assertSame(NotificationsNotifier::class, (string) $container->getAlias(TermsNotifier::class));
        self::assertEquals([new Reference(Policies::class), new Reference(Outstanding::class)], $container->getDefinition(OutstandingAudience::class)->getArguments());
        self::assertSame([TermsServices::AUDIENCE_TAG => [[]]], $container->getDefinition(OutstandingAudience::class)->getTags());

        $terms = $container->getDefinition(Terms::class)->getArguments();
        self::assertEquals($clock, $terms[5]);
        self::assertEquals(new Reference(AddressMasker::class), $terms[6]);
        self::assertEquals(new Reference(TermsNotifier::class), $terms[7]);

        self::assertSame('site.html.twig', $container->getDefinition(Pages::class)->getArgument(3));
        self::assertSame('fr', $container->getDefinition(AcceptanceController::class)->getArgument(6));
        self::assertSame('fr', $container->getDefinition(PolicyController::class)->getArgument(5));
        self::assertEquals($clock, $container->getDefinition(PolicyController::class)->getArgument(4));
        self::assertSame([['logout'], ['/media/'], ['/api/'], 'https://example.org/terms'], \array_slice($container->getDefinition(AcceptanceGate::class)->getArguments(), 4, 4));

        $admin = $container->getDefinition(TermsAdminController::class);
        self::assertEquals([
            new Reference(Pages::class),
            new Reference(Policies::class),
            new Reference(VersionCatalog::class),
            new Reference(Terms::class),
            new Reference('security.authorization_checker'),
            'ROLE_LEGAL',
            'admin.html.twig',
            ['fr', 'en', 'es'],
            'America/Toronto',
            true,
        ], $admin->getArguments());
        self::assertTrue($admin->isPublic());
        self::assertSame(['controller.service_arguments' => [[]]], $admin->getTags());

        $handler = $container->getDefinition(SendTermsRemindersHandler::class);
        self::assertEquals([new Reference(Policies::class), new Reference(VersionCatalog::class), new Reference(Outstanding::class), new Reference(TermsNotifier::class), $clock, 7], $handler->getArguments());
        self::assertSame(['messenger.message_handler' => [['handles' => SendTermsReminders::class]]], $handler->getTags());
        $schedule = $container->getDefinition(TermsSchedule::class);
        self::assertSame(['1 day'], $schedule->getArguments());
        self::assertSame(['scheduler.schedule_provider' => [['name' => 'phil_terms']]], $schedule->getTags());
    }

    public function testTheGateDisabledAndTheLocalesOfTheAdministration(): void
    {
        $container = $this->load(['gate' => false, 'admin' => true]);

        self::assertFalse($container->hasDefinition(AcceptanceGate::class));
        self::assertSame(['en'], $container->getDefinition(TermsAdminController::class)->getArgument(7));
        self::assertSame('ROLE_ADMIN', $container->getDefinition(TermsAdminController::class)->getArgument(5));
        self::assertSame(PhilTermsBundle::ADMIN_LAYOUT, $container->getDefinition(TermsAdminController::class)->getArgument(6));
        self::assertSame('UTC', $container->getDefinition(TermsAdminController::class)->getArgument(8));
    }

    public function testANotifierOfTheApplicationSendsTheReminders(): void
    {
        $container = $this->load(['notifier' => 'app.notifier', 'notifications' => true, 'reminders' => true]);

        self::assertSame('app.notifier', (string) $container->getAlias(TermsNotifier::class));
        self::assertFalse($container->hasDefinition(NotificationsNotifier::class));
        self::assertFalse($container->hasDefinition(OutstandingAudience::class));
        self::assertEquals(new Reference(TermsNotifier::class), $container->getDefinition(Terms::class)->getArgument(7));
        self::assertEquals(new Reference(TermsNotifier::class), $container->getDefinition(SendTermsRemindersHandler::class)->getArgument(3));
        self::assertSame(14, $container->getDefinition(SendTermsRemindersHandler::class)->getArgument(5));
        self::assertSame(['1 week'], $container->getDefinition(TermsSchedule::class)->getArguments());
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function badConfigurations(): iterable
    {
        yield 'a bad policy key' => [['policies' => ['Terms' => []]], 'phil_terms.policies.Terms: A policy key is'];
        yield 'a renewal too long' => [['policies' => ['terms' => ['renew_after_days' => 3651]]], 'renew_after_days'];
        yield 'a bad default locale' => [['default_locale' => 'EN'], 'phil_terms: "EN" is not a locale.'];
        yield 'a bad locale' => [['locales' => ['en', 'xx-']], 'phil_terms: "xx-" is not a locale.'];
        yield 'a numeric policy key' => [['policies' => ['1' => []]], 'phil_terms.policies.1: A policy key is'];
        yield 'a renewal of 0 days' => [['policies' => ['terms' => ['renew_after_days' => 0]]], 'renew_after_days'];
        yield 'an IPv4 prefix above 32' => [['network' => ['ipv4_prefix' => 33]], 'ipv4_prefix'];
        yield 'a negative IPv4 prefix' => [['network' => ['ipv4_prefix' => -1]], 'ipv4_prefix'];
        yield 'an IPv6 prefix above 128' => [['network' => ['ipv6_prefix' => 129]], 'ipv6_prefix'];
        yield 'a negative IPv6 prefix' => [['network' => ['ipv6_prefix' => -1]], 'ipv6_prefix'];
        yield 'more than 365 days ahead' => [['notifier' => 'n', 'reminders' => ['days_ahead' => 366]], 'days_ahead'];
        yield 'negative days ahead' => [['notifier' => 'n', 'reminders' => ['days_ahead' => -1]], 'days_ahead'];
        yield 'reminders without a notifier' => [['reminders' => true], 'phil_terms.reminders needs a notifier'];
        yield 'two policies naming the licence' => [['policies' => ['terms' => ['contribution_licence' => true], 'privacy' => [], 'charter' => ['contribution_licence' => true]]], 'phil_terms.policies: one policy at most names the licence of the contributions, not "terms", "charter".'];
        yield 'no member source' => [['member_source' => null], 'The path "phil_terms.member_source" cannot contain an empty value'];
        yield 'an empty member source' => [['member_source' => ''], 'The path "phil_terms.member_source" cannot contain an empty value'];
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('badConfigurations')]
    public function testABadConfigurationFailsTheBuild(array $config, string $message): void
    {
        try {
            $this->load($config);
            self::fail('Built.');
        } catch (InvalidConfigurationException $invalidConfigurationException) {
            self::assertStringContainsString($message, $invalidConfigurationException->getMessage());
        }
    }

    public function testTheMemberSourceIsRequired(): void
    {
        try {
            $this->extension()->load([[]], new ContainerBuilder());
            self::fail('Built.');
        } catch (InvalidConfigurationException $invalidConfigurationException) {
            self::assertStringContainsString('The child config "member_source" under "phil_terms" must be configured', $invalidConfigurationException->getMessage());
        }
    }

    public function testTheDefaultResolverNeedsTheDeclarationOfStableIdentifiers(): void
    {
        try {
            $this->extension()->load([['member_source' => 'app.members']], new ContainerBuilder());
            self::fail('Built.');
        } catch (InvalidConfigurationException $invalidConfigurationException) {
            self::assertSame('phil_terms.member_resolver is not set: the default resolver names a member by the user identifier of the security token, which is often an email address. Name a resolver that gives a stable identifier, or set phil_terms.stable_identifiers to true if the user identifiers are never email addresses.', $invalidConfigurationException->getMessage());
        }

        try {
            $this->extension()->load([['member_source' => 'app.members', 'stable_identifiers' => false]], new ContainerBuilder());
            self::fail('Built.');
        } catch (InvalidConfigurationException $invalidConfigurationException) {
            self::assertStringStartsWith('phil_terms.member_resolver is not set', $invalidConfigurationException->getMessage());
        }
    }

    public function testAResolverOfTheApplicationNeedsNoDeclaration(): void
    {
        $container = new ContainerBuilder();
        $this->extension()->load([['member_source' => 'app.members', 'member_resolver' => 'app.member']], $container);

        self::assertFalse($container->hasDefinition(TokenMemberResolver::class));
        self::assertSame('app.member', (string) $container->getAlias(MemberResolver::class));
    }

    public function testTheBoundsOfTheConfiguration(): void
    {
        $container = $this->load([
            'policies' => ['terms' => ['renew_after_days' => 1], 'privacy' => ['renew_after_days' => 3650]],
            'network' => ['ipv4_prefix' => 0, 'ipv6_prefix' => 0],
            'notifier' => 'n',
            'reminders' => ['days_ahead' => 0],
        ]);
        self::assertEquals([new Definition(Truncation::class, [0, 0])], $container->getDefinition(NetworkMasker::class)->getArguments());
        self::assertSame(0, $container->getDefinition(SendTermsRemindersHandler::class)->getArgument(5));
        self::assertEquals([new Definition(Policy::class, ['terms', true, 1, false]), new Definition(Policy::class, ['privacy', true, 3650, false])], $container->getDefinition(Policies::class)->getArguments());

        $container = $this->load(['network' => ['ipv4_prefix' => 32, 'ipv6_prefix' => 128], 'notifier' => 'n', 'reminders' => ['days_ahead' => 365]]);
        self::assertEquals([new Definition(Truncation::class, [32, 128])], $container->getDefinition(NetworkMasker::class)->getArguments());
        self::assertSame(365, $container->getDefinition(SendTermsRemindersHandler::class)->getArgument(5));

        self::assertEquals([new Definition(Truncation::class, [24, 48])], $this->load(['network' => true])->getDefinition(NetworkMasker::class)->getArguments());
    }

    public function testTheCauseOfABadPolicyIsKept(): void
    {
        try {
            $this->load(['policies' => ['Terms' => []]]);
            self::fail('Built.');
        } catch (InvalidConfigurationException $invalidConfigurationException) {
            self::assertNotNull($invalidConfigurationException->getPrevious());
        }

        try {
            $this->load(['locales' => ['EN']]);
            self::fail('Built.');
        } catch (InvalidConfigurationException $invalidConfigurationException) {
            self::assertNotNull($invalidConfigurationException->getPrevious());
        }
    }

    public function testTheTwoNoticeTypesAreDeclaredWithoutTheApplication(): void
    {
        self::assertSame([['types' => [
            'phil_terms.published' => ['channels' => ['site', 'email']],
            'phil_terms.reminder' => ['channels' => ['site', 'email']],
        ]]], $this->prepended([['notifications' => true]]));
        self::assertCount(1, $this->prepended([['notifications' => null]]));
        self::assertCount(1, $this->prepended([['notifications' => []]]));
        self::assertCount(1, $this->prepended([['notifications' => ['enabled' => true]]]));
    }

    public function testTheTypesKeepTheNamesOfTheConfiguration(): void
    {
        self::assertSame([['types' => [
            'legal.published' => ['channels' => ['site', 'email']],
            'phil_terms.reminder' => ['channels' => ['site', 'email']],
        ]]], $this->prepended([['notifications' => ['published_type' => 'first']], [], ['notifications' => ['published_type' => 'legal.published', 'reminder_type' => null]]]));
    }

    public function testNoTypeIsDeclaredWithTheNoticesOff(): void
    {
        self::assertSame([], $this->prepended([[]]));
        self::assertSame([], $this->prepended([['notifications' => false]]));
        self::assertSame([], $this->prepended([['notifications' => ['enabled' => false]]]));
        self::assertSame([], $this->prepended([['notifications' => true], ['notifications' => false]]));
    }

    public function testNothingIsDeclaredWithoutTheNotificationsBundle(): void
    {
        $extension = $this->extension();
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $container = new ContainerBuilder();
        $container->registerExtension($extension);
        $container->prependExtensionConfig('phil_terms', ['notifications' => true]);

        $extension->prepend($container);

        self::assertFalse($container->hasExtension('phil_notifications'));
        self::assertSame([['notifications' => true]], $container->getExtensionConfig('phil_terms'));
    }

    public function testTheCheckOfTheLegalLinksNeedsTheLegalNoticesBundle(): void
    {
        self::assertFalse($this->load([])->hasDefinition(LegalLinksCommand::class));

        $other = new ContainerBuilder(new ParameterBag(['kernel.bundles' => ['OtherBundle' => 'App']]));
        $this->extension()->load([['member_source' => 'app.members', 'stable_identifiers' => true]], $other);
        self::assertFalse($other->hasDefinition(LegalLinksCommand::class));

        $container = new ContainerBuilder(new ParameterBag(['kernel.bundles' => ['PhilLegalNoticesBundle' => 'App']]));
        $this->extension()->load([['pages' => ['layout' => 'site.html.twig'], 'member_source' => 'app.members', 'stable_identifiers' => true]], $container);
        $command = $container->getDefinition(LegalLinksCommand::class);
        self::assertEquals([new Reference(FooterLinks::class), new Reference(Policies::class), new Reference('twig'), new Reference('request_stack'), 'site.html.twig'], $command->getArguments());
        self::assertSame(['console.command' => [[]]], $command->getTags());
    }

    /**
     * @param list<array<string, mixed>> $configs
     *
     * @return array<array-key, mixed>
     */
    private function prepended(array $configs): array
    {
        $extension = $this->extension();
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $notifications = self::createStub(ExtensionInterface::class);
        $notifications->method('getAlias')->willReturn('phil_notifications');
        $container = new ContainerBuilder();
        $container->registerExtension($extension);
        $container->registerExtension($notifications);

        foreach (array_reverse($configs) as $config) {
            $container->prependExtensionConfig('phil_terms', $config);
        }

        $extension->prepend($container);

        return $container->getExtensionConfig('phil_notifications');
    }

    public function testTheRecipesAreNamedAfterTheSubdirectoryOfTheToolkitUnlessTheConfigurationSaysIt(): void
    {
        self::assertSame(['ui'], $this->compiled([], 'ui')->getDefinition(KitComponents::class)->getArguments());
        self::assertSame([''], $this->compiled([], null)->getDefinition(KitComponents::class)->getArguments());
        self::assertSame([''], $this->compiled([], false)->getDefinition(KitComponents::class)->getArguments());
        self::assertSame(['own'], $this->compiled(['kit_subdirectory' => 'own'], 'ui')->getDefinition(KitComponents::class)->getArguments());
        self::assertSame([''], $this->compiled(['kit_subdirectory' => ''], 'ui')->getDefinition(KitComponents::class)->getArguments());

        $empty = new ContainerBuilder();
        new KitSubdirectoryPass()->process($empty);
        self::assertFalse($empty->hasDefinition(KitComponents::class));
        self::assertSame(['ui:Field:Label', 'a:b:Badge', 'Badge'], [new KitComponents('/ui/')->name('Field:Label'), new KitComponents('a/b')->name('Badge'), new KitComponents()->name('Badge')]);
        self::assertSame(['ui:Alert', 'Alert'], [new TermsTwig(new KitComponents('ui'))->kit('Alert'), new TermsTwig()->kit('Alert')]);
    }

    /**
     * @param array<string, mixed> $config
     * @param string|false|null    $toolkit the subdirectory of the Toolkit's configuration; false when the Toolkit is not there
     */
    private function compiled(array $config, string|false|null $toolkit): ContainerBuilder
    {
        $container = $this->load($config);

        if (false !== $toolkit) {
            $container->setParameter('.ux_toolkit.components.subdirectory', $toolkit);
        }

        new PhilTermsBundle()->build($container);

        foreach ($container->getCompilerPassConfig()->getBeforeOptimizationPasses() as $pass) {
            if ($pass instanceof KitSubdirectoryPass) {
                $pass->process($container);
            }
        }

        return $container;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function load(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $this->extension()->load([$config + ['member_source' => 'app.members', 'stable_identifiers' => true]], $container);

        return $container;
    }

    private function extension(): ExtensionInterface
    {
        $extension = new PhilTermsBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        return $extension;
    }
}

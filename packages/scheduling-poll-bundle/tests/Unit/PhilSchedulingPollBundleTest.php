<?php

declare(strict_types=1);

namespace Phil\SchedulingPollBundle\Tests\Unit;

use Phil\AccessLinks\Dbal\DbalInvitationStore;
use Phil\AccessLinks\InMemoryInvitationStore;
use Phil\AccessLinks\LinkFormat;
use Phil\AccessLinks\TokenHasher;
use Phil\SchedulingPollBundle\Application\Contract\RespondentAddresses;
use Phil\SchedulingPollBundle\Application\Message\AnnounceChosenDate;
use Phil\SchedulingPollBundle\Application\Message\CloseDuePolls;
use Phil\SchedulingPollBundle\Application\Message\EraseRespondents;
use Phil\SchedulingPollBundle\Application\Message\SendChosenDate;
use Phil\SchedulingPollBundle\Application\NoRespondentAddresses;
use Phil\SchedulingPollBundle\Application\SchedulingPolls;
use Phil\SchedulingPollBundle\Application\SchedulingPollViews;
use Phil\SchedulingPollBundle\Domain\Contract\RespondentRepository;
use Phil\SchedulingPollBundle\Domain\Contract\SchedulingPollRepository;
use Phil\SchedulingPollBundle\Domain\Tally\AvailabilityCount;
use Phil\SchedulingPollBundle\Infrastructure\DependencyInjection\KitSubdirectoryPass;
use Phil\SchedulingPollBundle\Infrastructure\DependencyInjection\OptionalServicesPass;
use Phil\SchedulingPollBundle\Infrastructure\DependencyInjection\SchedulingPollServices;
use Phil\SchedulingPollBundle\Infrastructure\Doctrine\DoctrineRespondentRepository;
use Phil\SchedulingPollBundle\Infrastructure\Doctrine\DoctrineSchedulingPollRepository;
use Phil\SchedulingPollBundle\Infrastructure\Doctrine\InvitationSchemaListener;
use Phil\SchedulingPollBundle\Infrastructure\Http\AnswerController;
use Phil\SchedulingPollBundle\Infrastructure\Mailer\ChosenDateMail;
use Phil\SchedulingPollBundle\Infrastructure\Memory\InMemoryRespondentRepository;
use Phil\SchedulingPollBundle\Infrastructure\Memory\InMemorySchedulingPollRepository;
use Phil\SchedulingPollBundle\Infrastructure\Messenger\AnnounceChosenDateHandler;
use Phil\SchedulingPollBundle\Infrastructure\Messenger\CloseDuePollsHandler;
use Phil\SchedulingPollBundle\Infrastructure\Messenger\EraseRespondentsHandler;
use Phil\SchedulingPollBundle\Infrastructure\Messenger\SendChosenDateHandler;
use Phil\SchedulingPollBundle\Infrastructure\Privacy\SchedulingPollPrivacy;
use Phil\SchedulingPollBundle\Infrastructure\Scheduler\SchedulingPollSchedule;
use Phil\SchedulingPollBundle\Infrastructure\Twig\SchedulingPollTwig;
use Phil\SchedulingPollBundle\PhilSchedulingPollBundle;
use Phil\SchedulingPollBundle\Tests\Unit\Fixture\Fixtures;
use Phil\Url\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\DependencyInjection\MessengerPass;
use Symfony\Component\Messenger\MessengerBundle;
use Symfony\Component\Mime\Address;

#[CoversClass(PhilSchedulingPollBundle::class)]
#[CoversClass(SchedulingPollServices::class)]
#[CoversClass(KitSubdirectoryPass::class)]
#[CoversClass(OptionalServicesPass::class)]
final class PhilSchedulingPollBundleTest extends TestCase
{
    private const array LINKS = ['links' => ['keys' => [Fixtures::KEY], 'page' => Fixtures::PAGE]];

    public function testTheDoctrineStoreIsTheDefault(): void
    {
        $container = $this->load([self::LINKS]);

        self::assertSame('phil_scheduling_poll', $this->extension()->getAlias());
        self::assertSame(DoctrineSchedulingPollRepository::class, (string) $container->getAlias(SchedulingPollRepository::class));
        self::assertSame(DoctrineRespondentRepository::class, (string) $container->getAlias(RespondentRepository::class));
        self::assertEquals([new Reference('doctrine.orm.default_entity_manager')], $container->getDefinition(DoctrineSchedulingPollRepository::class)->getArguments());
        self::assertSame(DbalInvitationStore::class, $container->getDefinition('phil_scheduling_poll.invitation_store')->getClass());
        self::assertEquals([new Reference('doctrine.dbal.default_connection')], $container->getDefinition('phil_scheduling_poll.invitation_store')->getArguments());
        self::assertSame([['event' => 'postGenerateSchema']], $container->getDefinition(InvitationSchemaListener::class)->getTag('doctrine.event_listener'));
        self::assertSame([Fixtures::KEY], $container->getDefinition(TokenHasher::class)->getArguments());

        $polls = $container->getDefinition(SchedulingPolls::class);
        self::assertTrue($polls->isPublic());
        self::assertSame('P30D', $polls->getArgument(7));
        self::assertSame(500, $polls->getArgument(8));
        self::assertSame(NoRespondentAddresses::class, (string) $container->getAlias(RespondentAddresses::class));
        self::assertSame([['name' => 'phil_scheduling_poll']], $container->getDefinition(SchedulingPollSchedule::class)->getTag('scheduler.schedule_provider'));
        self::assertSame(['5 minutes', '1 hour'], \array_slice($container->getDefinition(SchedulingPollSchedule::class)->getArguments(), 2));
        self::assertEquals(new Reference('cache.app'), $container->getDefinition(SchedulingPollSchedule::class)->getArgument(0));
        self::assertEquals(new Reference('lock.factory', ContainerInterface::NULL_ON_INVALID_REFERENCE), $container->getDefinition(SchedulingPollSchedule::class)->getArgument(1));
        self::assertSame([['handles' => SendChosenDate::class]], $container->getDefinition(SendChosenDateHandler::class)->getTag('messenger.message_handler'));
        self::assertSame([['handles' => CloseDuePolls::class]], $container->getDefinition(CloseDuePollsHandler::class)->getTag('messenger.message_handler'));
        self::assertSame([['handles' => EraseRespondents::class]], $container->getDefinition(EraseRespondentsHandler::class)->getTag('messenger.message_handler'));
        self::assertSame([['handles' => AnnounceChosenDate::class]], $container->getDefinition(AnnounceChosenDateHandler::class)->getTag('messenger.message_handler'));
        self::assertNull($container->getDefinition(ChosenDateMail::class)->getArgument(1));
        self::assertTrue($container->getDefinition(SchedulingPollPrivacy::class)->isAutoconfigured());
        self::assertSame('user', $container->getDefinition(SchedulingPollPrivacy::class)->getArgument(2));
        self::assertTrue($container->getDefinition(AnswerController::class)->hasTag('controller.service_arguments'));
        self::assertNull($container->getDefinition(SchedulingPollTwig::class)->getArgument(1));
    }

    public function testTheMemoryStoreAndEveryOption(): void
    {
        $container = $this->load([self::LINKS + [
            'store' => 'memory',
            'link_lifetime' => 'P1W',
            'max_respondents' => 40,
            'closing' => ['frequency' => '1 minute', 'batch' => 10],
            'erasure' => ['frequency' => '1 day'],
            'schedule' => ['cache' => 'cache.scheduler', 'lock_factory' => 'lock.other.factory'],
            'mailer' => ['from' => 'Polls <polls@example.org>'],
            'respondent_addresses' => 'app.addresses',
            'privacy' => ['account_kind' => 'member'],
            'kit_subdirectory' => 'ui',
        ]]);

        self::assertSame(InMemorySchedulingPollRepository::class, (string) $container->getAlias(SchedulingPollRepository::class));
        self::assertSame(InMemoryRespondentRepository::class, (string) $container->getAlias(RespondentRepository::class));
        self::assertTrue($container->getDefinition(InMemoryRespondentRepository::class)->hasTag('kernel.reset'));
        self::assertTrue($container->getDefinition(InMemorySchedulingPollRepository::class)->hasTag('kernel.reset'));
        self::assertSame(InMemoryInvitationStore::class, $container->getDefinition('phil_scheduling_poll.invitation_store')->getClass());
        self::assertFalse($container->hasDefinition(InvitationSchemaListener::class));
        self::assertSame(['P1W', 40], \array_slice($container->getDefinition(SchedulingPolls::class)->getArguments(), 7));
        self::assertSame(['1 minute', '1 day'], \array_slice($container->getDefinition(SchedulingPollSchedule::class)->getArguments(), 2));
        self::assertEquals(new Reference('cache.scheduler'), $container->getDefinition(SchedulingPollSchedule::class)->getArgument(0));
        self::assertSame('app.addresses', (string) $container->getAlias(RespondentAddresses::class));
        self::assertFalse($container->hasDefinition(NoRespondentAddresses::class));
        self::assertInstanceOf(Definition::class, $container->getDefinition(ChosenDateMail::class)->getArgument(1));
        self::assertSame('member', $container->getDefinition(SchedulingPollPrivacy::class)->getArgument(2));
        self::assertSame('ui', $container->getDefinition(SchedulingPollTwig::class)->getArgument(1));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidConfigurations(): iterable
    {
        yield 'no links' => [[]];
        yield 'no key' => [['links' => ['keys' => [], 'page' => Fixtures::PAGE]]];
        yield 'a page with a fragment' => [['links' => ['keys' => [Fixtures::KEY], 'page' => Fixtures::PAGE.'#top']]];
        yield 'a page on localhost' => [['links' => ['keys' => [Fixtures::KEY], 'page' => 'http://localhost/answer']]];
        yield 'a page that is no address' => [['links' => ['keys' => [Fixtures::KEY], 'page' => 'answer']]];
        yield 'a page that is no text' => [['links' => ['keys' => [Fixtures::KEY], 'page' => 12]]];
        yield 'a lifetime that is no duration' => [self::LINKS + ['link_lifetime' => '30 days']];
        yield 'a lifetime without a part' => [self::LINKS + ['link_lifetime' => 'P']];
        yield 'a lifetime of an empty time' => [self::LINKS + ['link_lifetime' => 'PT']];
        yield 'a lifetime that is no text' => [self::LINKS + ['link_lifetime' => 30]];
        yield 'no respondent' => [self::LINKS + ['max_respondents' => 0]];
        yield 'an unknown store' => [self::LINKS + ['store' => 'files']];
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('invalidConfigurations')]
    public function testAnInvalidConfigurationIsRefused(array $config): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->load([$config]);
    }

    public function testADurationOfTimeOnlyIsALifetimeAndAPageMayHaveAQuery(): void
    {
        self::assertSame('PT12H', $this->load([self::LINKS + ['link_lifetime' => 'PT12H']])->getDefinition(SchedulingPolls::class)->getArgument(7));
        $page = $this->load([['links' => ['keys' => [Fixtures::KEY], 'page' => 'https://example.org/answer?lang=fr', 'parameter' => 'link']]])->getDefinition(LinkFormat::class);
        self::assertInstanceOf(Definition::class, $page->getArgument(0));
        self::assertSame(['https://example.org/answer?lang=fr'], $page->getArgument(0)->getArguments());
        self::assertSame('link', $page->getArgument(1));
    }

    public function testTheTablesAreMappedForDoctrineWhenItIsThere(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new class extends Extension {
            public function load(array $configs, ContainerBuilder $container): void
            {
            }

            public function getAlias(): string
            {
                return 'doctrine';
            }
        });
        $extension = $this->extension();
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $extension->prepend($container);

        $doctrine = $container->getExtensionConfig('doctrine')[0] ?? null;
        self::assertIsArray($doctrine);
        $orm = $doctrine['orm'] ?? null;
        self::assertIsArray($orm);
        $mappings = $orm['mappings'] ?? null;
        self::assertIsArray($mappings);
        $mapping = $mappings['PhilSchedulingPollBundle'] ?? null;
        self::assertIsArray($mapping);
        self::assertIsString($mapping['dir']);
        self::assertSame('attribute', $mapping['type']);
        self::assertFalse($mapping['is_bundle']);
        self::assertSame('Phil\SchedulingPollBundle\Domain\Model', $mapping['prefix']);
        self::assertDirectoryExists($mapping['dir']);
        self::assertSame(\dirname(__DIR__, 2).'/src/Domain/Model', $mapping['dir']);

        $without = new ContainerBuilder();
        $extension->prepend($without);
        self::assertSame([], $without->getExtensionConfig('doctrine'));
    }

    public function testTheKitSubdirectoryIsTheToolkitsUnlessConfigured(): void
    {
        $pass = new KitSubdirectoryPass();
        $container = $this->load([self::LINKS]);
        $container->setParameter('.ux_toolkit.components.subdirectory', 'components/ui');

        $pass->process($container);
        self::assertSame('components/ui', $container->getDefinition(SchedulingPollTwig::class)->getArgument(1));

        $configured = $this->load([self::LINKS + ['kit_subdirectory' => 'kit']]);
        $configured->setParameter('.ux_toolkit.components.subdirectory', 'components/ui');

        $pass->process($configured);
        self::assertSame('kit', $configured->getDefinition(SchedulingPollTwig::class)->getArgument(1));

        $without = $this->load([self::LINKS]);
        $pass->process($without);
        self::assertSame('', $without->getDefinition(SchedulingPollTwig::class)->getArgument(1));

        $notText = $this->load([self::LINKS]);
        $notText->setParameter('.ux_toolkit.components.subdirectory', ['ui']);

        $pass->process($notText);
        self::assertSame('', $notText->getDefinition(SchedulingPollTwig::class)->getArgument(1));

        $empty = new ContainerBuilder();
        $pass->process($empty);
        self::assertFalse($empty->hasDefinition(SchedulingPollTwig::class));

        $bundle = new ContainerBuilder();
        new PhilSchedulingPollBundle()->build($bundle);
        self::assertCount(1, array_filter($bundle->getCompilerPassConfig()->getPasses(), static fn (object $added): bool => $added instanceof KitSubdirectoryPass));
        self::assertCount(1, array_filter($bundle->getCompilerPassConfig()->getPasses(), static fn (object $added): bool => $added instanceof OptionalServicesPass));
    }

    public function testTheHandlersAndThePagesAreLeftOutWithoutTheirCollaborators(): void
    {
        $pass = new OptionalServicesPass();
        $without = $this->load([self::LINKS]);
        $pass->process($without);

        foreach ([AnnounceChosenDateHandler::class, CloseDuePollsHandler::class, EraseRespondentsHandler::class, SendChosenDateHandler::class, AnswerController::class] as $removed) {
            self::assertFalse($without->hasDefinition($removed), $removed);
        }

        self::assertTrue($without->hasDefinition(SchedulingPolls::class));
        self::assertTrue($without->hasDefinition(ChosenDateMail::class));
        self::assertTrue($without->hasDefinition(SchedulingPollSchedule::class));

        $with = $this->load([self::LINKS]);
        $with->register('messenger.default_bus');
        $with->register('twig');

        $pass->process($with);

        self::assertFalse($with->hasDefinition(AnswerController::class));
        self::assertTrue($with->hasDefinition(SendChosenDateHandler::class));
        self::assertTrue($with->hasDefinition(AnnounceChosenDateHandler::class));

        $with->register('translator');
        $pass->process($with);
        self::assertFalse($with->hasDefinition(AnswerController::class));

        $all = $this->load([self::LINKS]);
        $all->register('messenger.default_bus');
        $all->register('twig');
        $all->register('translator');

        $pass->process($all);
        self::assertTrue($all->hasDefinition(AnswerController::class));
        self::assertTrue($all->hasDefinition(CloseDuePollsHandler::class));
        self::assertTrue($all->hasDefinition(EraseRespondentsHandler::class));
    }

    public function testTheDoctrineWiringNamesEveryCollaborator(): void
    {
        $container = $this->load([self::LINKS + ['store' => 'doctrine']]);
        $optional = ContainerInterface::NULL_ON_INVALID_REFERENCE;
        $polls = new Reference(SchedulingPollRepository::class);
        $respondents = new Reference(RespondentRepository::class);
        $entityManager = new Reference('doctrine.orm.default_entity_manager');

        self::assertEquals([new Definition(Url::class, [Fixtures::PAGE])->setFactory([Url::class, 'fromString']), 'token'], $container->getDefinition(LinkFormat::class)->getArguments());
        self::assertEquals([new Reference('phil_scheduling_poll.invitation_store'), new Reference(TokenHasher::class), new Reference(LinkFormat::class), new Reference('clock')], $container->getDefinition('phil_scheduling_poll.access_links')->getArguments());
        self::assertTrue($container->hasDefinition(AvailabilityCount::class));
        self::assertEquals(
            [$polls, $respondents, new Reference('phil_scheduling_poll.access_links'), new Reference('clock'), new Reference(AvailabilityCount::class), new Reference('messenger.default_bus', $optional), new Reference('event_dispatcher', $optional), 'P30D', 500],
            $container->getDefinition(SchedulingPolls::class)->getArguments(),
        );
        self::assertEquals([new Reference(SchedulingPolls::class)], $container->getDefinition(SchedulingPollViews::class)->getArguments());
        self::assertTrue($container->getDefinition(SchedulingPollViews::class)->isPublic());
        self::assertTrue($container->hasDefinition(NoRespondentAddresses::class));
        self::assertEquals([$entityManager], $container->getDefinition(DoctrineSchedulingPollRepository::class)->getArguments());
        self::assertEquals([$entityManager], $container->getDefinition(DoctrineRespondentRepository::class)->getArguments());
        self::assertEquals([new Reference('phil_scheduling_poll.invitation_store')], $container->getDefinition(InvitationSchemaListener::class)->getArguments());
        self::assertTrue($container->getAlias(SchedulingPollRepository::class)->isPublic());
        self::assertTrue($container->getAlias(RespondentRepository::class)->isPublic());

        self::assertEquals([$polls, $respondents, new Reference('messenger.default_bus')], $container->getDefinition(AnnounceChosenDateHandler::class)->getArguments());
        self::assertEquals([new Reference(SchedulingPolls::class), 100], $container->getDefinition(CloseDuePollsHandler::class)->getArguments());
        self::assertEquals([new Reference(SchedulingPolls::class)], $container->getDefinition(EraseRespondentsHandler::class)->getArguments());
        self::assertEquals([new Reference('translator'), null, '%kernel.default_locale%'], $container->getDefinition(ChosenDateMail::class)->getArguments());
        self::assertEquals(
            [$polls, $respondents, new Reference(RespondentAddresses::class), new Reference(ChosenDateMail::class), new Reference('clock'), new Reference(MailerInterface::class, $optional)],
            $container->getDefinition(SendChosenDateHandler::class)->getArguments(),
        );
        self::assertEquals([$respondents, $polls, 'user'], $container->getDefinition(SchedulingPollPrivacy::class)->getArguments());
        self::assertEquals([new Reference(SchedulingPollViews::class), null], $container->getDefinition(SchedulingPollTwig::class)->getArguments());
        self::assertTrue($container->getDefinition(SchedulingPollTwig::class)->isAutoconfigured());
        self::assertEquals(
            [new Reference(SchedulingPolls::class), new Reference(SchedulingPollViews::class), new Reference(LinkFormat::class), new Reference('twig'), new Reference('translator'), new Reference('clock'), new Reference('security.csrf.token_manager', $optional)],
            $container->getDefinition(AnswerController::class)->getArguments(),
        );
        self::assertTrue($container->getDefinition(AnswerController::class)->isPublic());
    }

    public function testTheMemoryWiringNamesEveryCollaborator(): void
    {
        $container = $this->load([self::LINKS + ['store' => 'memory', 'mailer' => ['from' => 'Polls <polls@example.org>']]]);

        self::assertSame([['method' => 'reset']], $container->getDefinition(InMemorySchedulingPollRepository::class)->getTag('kernel.reset'));
        self::assertSame([['method' => 'reset']], $container->getDefinition(InMemoryRespondentRepository::class)->getTag('kernel.reset'));
        self::assertEquals([new Reference(InMemorySchedulingPollRepository::class)], $container->getDefinition(InMemoryRespondentRepository::class)->getArguments());
        self::assertTrue($container->getAlias(SchedulingPollRepository::class)->isPublic());
        self::assertTrue($container->getAlias(RespondentRepository::class)->isPublic());
        self::assertEquals(new Definition(Address::class, ['Polls <polls@example.org>'])->setFactory([Address::class, 'create']), $container->getDefinition(ChosenDateMail::class)->getArgument(1));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, bool}>
     */
    public static function bounds(): iterable
    {
        yield 'one respondent' => [['max_respondents' => 1], true];
        yield 'ten thousand respondents' => [['max_respondents' => 10000], true];
        yield 'more than ten thousand respondents' => [['max_respondents' => 10001], false];
        yield 'a batch of one' => [['closing' => ['batch' => 1]], true];
        yield 'a batch of a thousand' => [['closing' => ['batch' => 1000]], true];
        yield 'an empty batch' => [['closing' => ['batch' => 0]], false];
        yield 'a batch over a thousand' => [['closing' => ['batch' => 1001]], false];
        yield 'the Doctrine store, named' => [['store' => 'doctrine'], true];
    }

    /**
     * @param array<string, mixed> $options
     */
    #[DataProvider('bounds')]
    public function testTheBoundsOfTheOptions(array $options, bool $accepted): void
    {
        if (!$accepted) {
            $this->expectException(InvalidConfigurationException::class);
        }

        $container = $this->load([self::LINKS + $options]);

        self::assertTrue($container->hasDefinition(SchedulingPolls::class));
    }

    public function testTheAnswerPageNeedsTwigAndTheTranslatorEach(): void
    {
        $pass = new OptionalServicesPass();
        $container = $this->load([self::LINKS]);
        $container->register('messenger.default_bus');
        $container->register('translator');

        $pass->process($container);

        self::assertFalse($container->hasDefinition(AnswerController::class));
    }

    /**
     * @param list<array<string, mixed>> $configs
     */
    private function load(array $configs): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.default_locale', 'en');
        $this->extension()->load($configs, $container);

        return $container;
    }

    private function extension(): ExtensionInterface
    {
        $extension = new PhilSchedulingPollBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        return $extension;
    }

    public function testTheHandlersAreRemovedBeforeMessengerWiresThem(): void
    {
        $container = new ContainerBuilder();
        new MessengerBundle()->build($container);
        new PhilSchedulingPollBundle()->build($container);

        $passes = array_map(static fn (object $pass): string => $pass::class, $container->getCompilerPassConfig()->getBeforeOptimizationPasses());
        $messenger = array_search(MessengerPass::class, $passes, true);
        self::assertIsInt($messenger);

        $removal = array_search(OptionalServicesPass::class, $passes, true);
        self::assertIsInt($removal);
        self::assertLessThan($messenger, $removal);
    }

    public function testThePassesRunAtPriorityOneNotHigher(): void
    {
        $container = new ContainerBuilder();
        new PhilSchedulingPollBundle()->build($container);
        $container->addCompilerPass(new class implements CompilerPassInterface {
            public function process(ContainerBuilder $container): void
            {
            }
        }, PassConfig::TYPE_BEFORE_OPTIMIZATION, 2);

        $passes = array_map(static fn (object $pass): string => $pass::class, $container->getCompilerPassConfig()->getBeforeOptimizationPasses());
        $marker = array_search(true, array_map(static fn (string $class): bool => str_contains($class, '@anonymous'), $passes), true);
        self::assertIsInt($marker);

        $removal = array_search(OptionalServicesPass::class, $passes, true);
        self::assertIsInt($removal);
        self::assertGreaterThan($marker, $removal, 'OptionalServicesPass must run at priority 1, after a pass of priority 2.');
    }
}

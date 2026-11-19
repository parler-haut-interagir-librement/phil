<?php

declare(strict_types=1);

namespace Phil\LibraryBundle\Tests\Unit;

use Phil\LibraryBundle\Application\Catalogue;
use Phil\LibraryBundle\Application\Contract\LoanTransaction;
use Phil\LibraryBundle\Application\Contract\MemberResolver;
use Phil\LibraryBundle\Application\Contract\ReminderSender;
use Phil\LibraryBundle\Application\Contract\StockLink;
use Phil\LibraryBundle\Application\Contract\TitleSearch;
use Phil\LibraryBundle\Application\LoanPolicy;
use Phil\LibraryBundle\Application\Loans;
use Phil\LibraryBundle\Application\Reminders;
use Phil\LibraryBundle\Infrastructure\Console\ForgetHistoryCommand;
use Phil\LibraryBundle\Infrastructure\Console\SendRemindersCommand;
use Phil\LibraryBundle\Infrastructure\DependencyInjection\KitSubdirectoryPass;
use Phil\LibraryBundle\Infrastructure\DependencyInjection\LibraryPass;
use Phil\LibraryBundle\Infrastructure\Doctrine\DbalLoanTransaction;
use Phil\LibraryBundle\Infrastructure\Doctrine\DbalTitleSearch;
use Phil\LibraryBundle\Infrastructure\Doctrine\DoctrineCopyRepository;
use Phil\LibraryBundle\Infrastructure\Doctrine\DoctrineLoanRepository;
use Phil\LibraryBundle\Infrastructure\Doctrine\DoctrineTitleRepository;
use Phil\LibraryBundle\Infrastructure\Form\CopyType;
use Phil\LibraryBundle\Infrastructure\Form\LendType;
use Phil\LibraryBundle\Infrastructure\Form\ReturnType;
use Phil\LibraryBundle\Infrastructure\Form\SearchType;
use Phil\LibraryBundle\Infrastructure\Form\TitleType;
use Phil\LibraryBundle\Infrastructure\Http\AdminController;
use Phil\LibraryBundle\Infrastructure\Http\CatalogueController;
use Phil\LibraryBundle\Infrastructure\Http\DeskController;
use Phil\LibraryBundle\Infrastructure\Http\MemberLoansController;
use Phil\LibraryBundle\Infrastructure\Http\Pages;
use Phil\LibraryBundle\Infrastructure\Messenger\MessengerReminderSender;
use Phil\LibraryBundle\Infrastructure\Privacy\LoanHistoryPrivacy;
use Phil\LibraryBundle\Infrastructure\Stockroom\NoStockLink;
use Phil\LibraryBundle\Infrastructure\Stockroom\StockroomLink;
use Phil\LibraryBundle\Infrastructure\Twig\KitComponents;
use Phil\LibraryBundle\Infrastructure\Twig\LibraryExtension;
use Phil\LibraryBundle\PhilLibraryBundle;
use Phil\Stockroom\Stockroom;
use Phil\StockroomBundle\Infrastructure\Dbal\StockroomTransactions;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Messenger\MessageBusInterface;

#[CoversClass(PhilLibraryBundle::class)]
#[CoversClass(LibraryPass::class)]
#[CoversClass(KitSubdirectoryPass::class)]
final class PhilLibraryBundleTest extends TestCase
{
    public function testTheAlias(): void
    {
        self::assertSame('phil_library', $this->extension()->getAlias());
    }

    public function testTheDefaults(): void
    {
        $container = $this->load([]);

        self::assertSame([21, 2, 10, true, 90, null, 'UTC'], $container->getDefinition(LoanPolicy::class)->getArguments());
        self::assertSame([[1440], 7, 3], \array_slice($container->getDefinition(Reminders::class)->getArguments(), 3));
        self::assertSame(500, $container->getDefinition(SendRemindersCommand::class)->getArgument(1));
        self::assertSame(25, $container->getDefinition(CatalogueController::class)->getArgument(6));
        self::assertSame('ROLE_LIBRARIAN', $container->getDefinition(DeskController::class)->getArgument(4));
        self::assertSame('@PhilLibrary/layout.html.twig', $container->getDefinition(Pages::class)->getArgument(1));
        self::assertNull($container->getDefinition(KitComponents::class)->getArgument(0));
        self::assertSame(NoStockLink::class, (string) $container->getAlias(StockLink::class));
        self::assertSame([null, null], [$container->getParameter(LibraryPass::MEMBER_RESOLVER), $container->getParameter(LibraryPass::STOCKROOM)]);
        self::assertTrue($container->getDefinition(LoanHistoryPrivacy::class)->hasTag(PhilLibraryBundle::EXPORTER_TAG));
        self::assertTrue($container->getDefinition(LoanHistoryPrivacy::class)->hasTag(PhilLibraryBundle::ERASER_TAG));
        self::assertSame(MessengerReminderSender::class, (string) $container->getAlias(ReminderSender::class));
    }

    public function testTheOptions(): void
    {
        $container = $this->load([[
            'timezone' => 'America/Toronto',
            'loans' => ['days' => 14, 'max_renewals' => 0, 'max_open' => 3, 'block_when_overdue' => false, 'reservation_days' => 30],
            'reminders' => ['before_due' => [2880, 60], 'overdue_every_days' => 3, 'overdue_max' => 0],
            'history_days' => 0,
            'batch' => 50,
            'per_page' => 10,
            'member_resolver' => 'app.members',
            'stockroom' => 'app.stockroom',
            'roles' => ['desk' => 'ROLE_DESK', 'admin' => 'ROLE_ADMIN'],
            'pages' => ['layout' => 'base.html.twig', 'kit_subdirectory' => 'ui'],
        ]]);

        self::assertSame([14, 0, 3, false, 30, 0, 'America/Toronto'], $container->getDefinition(LoanPolicy::class)->getArguments());
        self::assertSame([[2880, 60], 3, 0], \array_slice($container->getDefinition(Reminders::class)->getArguments(), 3));
        self::assertSame([50, 10, 'ROLE_DESK', 'base.html.twig', 'ui'], [$container->getDefinition(SendRemindersCommand::class)->getArgument(1), $container->getDefinition(CatalogueController::class)->getArgument(6), $container->getDefinition(DeskController::class)->getArgument(4), $container->getDefinition(Pages::class)->getArgument(1), $container->getDefinition(KitComponents::class)->getArgument(0)]);
        self::assertSame(['app.members', 'app.stockroom'], [$container->getParameter(LibraryPass::MEMBER_RESOLVER), $container->getParameter(LibraryPass::STOCKROOM)]);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, int, int}>
     */
    public static function bounds(): iterable
    {
        yield 'days of a loan' => [['loans' => ['days' => null]], 1, 365];
        yield 'renewals' => [['loans' => ['max_renewals' => null]], 0, 99];
        yield 'loans open' => [['loans' => ['max_open' => null]], 1, 999];
        yield 'days of a reservation' => [['loans' => ['reservation_days' => null]], 1, 730];
        yield 'minutes of a reminder' => [['reminders' => ['before_due' => [null]]], 1, 525_600];
        yield 'days between notices' => [['reminders' => ['overdue_every_days' => null]], 1, 365];
        yield 'most notices' => [['reminders' => ['overdue_max' => null]], 0, 99];
        yield 'days of history' => [['history_days' => null], 0, 36_500];
        yield 'batch' => [['batch' => null], 1, 10_000];
        yield 'titles a page' => [['per_page' => null], 1, 100];
    }

    /**
     * @param array<string, mixed> $shape the option, its value to be filled in
     */
    #[DataProvider('bounds')]
    public function testTheBoundsOfTheOptions(array $shape, int $min, int $max): void
    {
        foreach ([$min, $max] as $value) {
            $this->load([self::filled($shape, $value)]);
        }

        foreach ([$min - 1, $max + 1] as $value) {
            try {
                $this->load([self::filled($shape, $value)]);
                self::fail(\sprintf('%d was taken.', $value));
            } catch (InvalidConfigurationException) {
            }
        }
    }

    public function testTheOptionsThatAreTextsAreNotEmpty(): void
    {
        foreach ([['timezone' => ''], ['roles' => ['desk' => '']], ['roles' => ['admin' => '']], ['pages' => ['layout' => '']]] as $config) {
            try {
                $this->load([$config]);
                self::fail('An empty text was taken.');
            } catch (InvalidConfigurationException) {
            }
        }
    }

    public function testTheServicesAreWiredToTheirCollaborators(): void
    {
        $container = $this->load([['timezone' => 'America/Toronto', 'roles' => ['desk' => 'ROLE_DESK', 'admin' => 'ROLE_ADMIN']]]);
        $manager = [new Reference('doctrine.orm.entity_manager')];
        $clock = new Reference('clock');
        $optional = static fn (string $id): Reference => new Reference($id, ContainerInterface::NULL_ON_INVALID_REFERENCE);

        foreach ([DoctrineTitleRepository::class, DoctrineCopyRepository::class, DoctrineLoanRepository::class] as $adapter) {
            self::assertEquals($manager, $container->getDefinition($adapter)->getArguments(), $adapter);
            self::assertTrue($container->getAlias(new ReflectionClass($adapter)->getInterfaceNames()[0])->isPublic(), $adapter);
        }

        self::assertEquals([new Reference('doctrine.dbal.default_connection'), $clock], $container->getDefinition(DbalTitleSearch::class)->getArguments());
        self::assertSame(DbalTitleSearch::class, (string) $container->getAlias(TitleSearch::class));
        self::assertTrue($container->getAlias(TitleSearch::class)->isPublic());
        self::assertTrue($container->hasDefinition(NoStockLink::class));
        self::assertEquals([new Reference(DoctrineTitleRepository::class), new Reference(DoctrineCopyRepository::class), $clock], $container->getDefinition(Catalogue::class)->getArguments());
        self::assertTrue($container->getDefinition(Catalogue::class)->isPublic());
        self::assertEquals([new Reference(DoctrineCopyRepository::class), new Reference(DoctrineLoanRepository::class), new Reference(StockLink::class), new Reference(LoanPolicy::class), $clock, $optional('event_dispatcher'), $optional(LoanTransaction::class)], $container->getDefinition(Loans::class)->getArguments());
        self::assertTrue($container->getDefinition(Loans::class)->isPublic());
        self::assertEquals([new Reference(MessageBusInterface::class)], $container->getDefinition(MessengerReminderSender::class)->getArguments());
        self::assertTrue($container->getDefinition(Reminders::class)->isPublic());
        self::assertEquals([new Reference(DoctrineLoanRepository::class), new Reference(ReminderSender::class), $clock], \array_slice($container->getDefinition(Reminders::class)->getArguments(), 0, 3));
        self::assertEquals([new Reference(Reminders::class), 500], $container->getDefinition(SendRemindersCommand::class)->getArguments());
        self::assertEquals([new Reference(Loans::class)], $container->getDefinition(ForgetHistoryCommand::class)->getArguments());
        self::assertTrue($container->getDefinition(SendRemindersCommand::class)->hasTag('console.command'));
        self::assertTrue($container->getDefinition(ForgetHistoryCommand::class)->hasTag('console.command'));
        self::assertEquals([new Reference(DoctrineLoanRepository::class)], $container->getDefinition(LoanHistoryPrivacy::class)->getArguments());

        foreach ([SearchType::class, LendType::class, ReturnType::class, TitleType::class, CopyType::class] as $type) {
            self::assertTrue($container->getDefinition($type)->hasTag('form.type'), $type);
        }

        $extension = $container->getDefinition(LibraryExtension::class);
        self::assertEquals([new Reference(KitComponents::class), 'America/Toronto'], $extension->getArguments());
        self::assertTrue($extension->hasTag('twig.attribute_extension'));
        self::assertTrue($extension->hasTag('twig.runtime'));
        self::assertEquals([new Reference('twig'), '@PhilLibrary/layout.html.twig', $optional('security.authorization_checker')], $container->getDefinition(Pages::class)->getArguments());

        $pages = new Reference(Pages::class);
        $forms = new Reference('form.factory');
        $loans = new Reference(Loans::class);
        $catalogue = new Reference(Catalogue::class);
        self::assertEquals([$pages, $forms, new Reference(TitleSearch::class), $catalogue, $loans, $clock, 25], $container->getDefinition(CatalogueController::class)->getArguments());
        self::assertEquals([$pages, $forms, $loans, new Reference('translator'), 'ROLE_DESK'], $container->getDefinition(DeskController::class)->getArguments());
        self::assertEquals([$pages, $forms, $catalogue, new Reference(DoctrineLoanRepository::class), new Reference('router'), $clock, 'ROLE_ADMIN'], $container->getDefinition(AdminController::class)->getArguments());
        self::assertEquals([$pages, $optional(MemberResolver::class), $loans, $clock], $container->getDefinition(MemberLoansController::class)->getArguments());

        foreach ([CatalogueController::class, DeskController::class, AdminController::class, MemberLoansController::class] as $controller) {
            self::assertTrue($container->getDefinition($controller)->isPublic(), $controller);
            self::assertTrue($container->getDefinition($controller)->hasTag('controller.service_arguments'), $controller);
        }
    }

    /**
     * @param array<array-key, mixed> $shape
     *
     * @return array<array-key, mixed>
     */
    private static function filled(array $shape, int $value): array
    {
        return array_map(static fn (mixed $part): mixed => \is_array($part) ? self::filled($part, $value) : $value, $shape);
    }

    public function testTheMappingIsPrependedWithDoctrine(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new class implements ExtensionInterface {
            public function load(array $configs, ContainerBuilder $container): void
            {
            }

            public function getNamespace(): string
            {
                return '';
            }

            public function getXsdValidationBasePath(): false
            {
                return false;
            }

            public function getAlias(): string
            {
                return 'doctrine';
            }
        });
        $extension = $this->extension();
        Assert::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $extension->prepend($container);

        $config = $container->getExtensionConfig('doctrine')[0] ?? null;
        self::assertIsArray($config);
        $orm = $config['orm'];
        self::assertIsArray($orm);
        self::assertIsArray($orm['mappings']);
        $mapping = $orm['mappings']['PhilLibraryBundle'];
        self::assertIsArray($mapping);
        self::assertIsString($mapping['dir']);
        self::assertSame(['attribute', false, 'Phil\LibraryBundle\Domain\Model'], [$mapping['type'], $mapping['is_bundle'], $mapping['prefix']]);
        self::assertDirectoryExists($mapping['dir']);
        self::assertStringEndsWith('/src/Domain/Model', $mapping['dir']);
        self::assertSame([], new ContainerBuilder()->getExtensionConfig('doctrine'));
    }

    public function testThePassWiresWhatTheApplicationNames(): void
    {
        $container = $this->load([['member_resolver' => 'app.members', 'stockroom' => 'app.stockroom']]);
        $container->register(MessageBusInterface::class);
        $container->register('twig');
        $container->register('form.factory');
        new LibraryPass()->process($container);

        self::assertSame('app.members', (string) $container->getAlias(MemberResolver::class));
        self::assertSame(StockroomLink::class, (string) $container->getAlias(StockLink::class));
        self::assertEquals([new Reference('app.stockroom'), null], $container->getDefinition(StockroomLink::class)->getArguments());
        self::assertFalse($container->has(LoanTransaction::class));
        self::assertTrue($container->hasDefinition(Reminders::class));
        self::assertTrue($container->hasDefinition(MessengerReminderSender::class));
        self::assertTrue($container->hasDefinition(Pages::class));
    }

    public function testThePassUsesTheStockroomOfTheBundleInATransactionOnTheSameConnection(): void
    {
        $container = $this->load([]);
        $container->register(Stockroom::class);
        $container->register(StockroomTransactions::class);
        $container->register('doctrine.dbal.main_connection');
        $container->setAlias('doctrine.dbal.default_connection', 'doctrine.dbal.main_connection');
        $container->setParameter(LibraryPass::STOCKROOM_CONNECTION, 'doctrine.dbal.main_connection');
        new LibraryPass()->process($container);

        self::assertSame(StockroomLink::class, (string) $container->getAlias(StockLink::class));
        self::assertEquals([new Reference(Stockroom::class), new Reference(StockroomTransactions::class)], $container->getDefinition(StockroomLink::class)->getArguments());
        self::assertSame(DbalLoanTransaction::class, (string) $container->getAlias(LoanTransaction::class));
        self::assertEquals([new Reference('doctrine.dbal.default_connection')], $container->getDefinition(DbalLoanTransaction::class)->getArguments());
    }

    public function testTheStockroomOfTheBundleOnAnotherConnectionMovesOutsideTheLoan(): void
    {
        $container = $this->load([]);
        $container->register(Stockroom::class);
        $container->register(StockroomTransactions::class);
        $container->register('doctrine.dbal.default_connection');
        $container->register('doctrine.dbal.stock_connection');
        $container->setParameter(LibraryPass::STOCKROOM_CONNECTION, 'doctrine.dbal.stock_connection');
        new LibraryPass()->process($container);

        self::assertEquals([new Reference(Stockroom::class), new Reference(StockroomTransactions::class)], $container->getDefinition(StockroomLink::class)->getArguments());
        self::assertFalse($container->has(LoanTransaction::class));
    }

    public function testTheStockroomOfTheBundleWithoutConnectionsRunsNoLoanTransaction(): void
    {
        foreach ([[null, true], ['doctrine.dbal.default_connection', false]] as [$parameter, $library]) {
            $container = $this->load([]);
            $container->register(Stockroom::class);
            $container->register(StockroomTransactions::class);
            $container->setParameter(LibraryPass::STOCKROOM_CONNECTION, $parameter);

            if ($library) {
                $container->register('doctrine.dbal.default_connection');
            }

            new LibraryPass()->process($container);

            self::assertTrue($container->hasDefinition(StockroomLink::class));
            self::assertFalse($container->has(LoanTransaction::class));
        }
    }

    public function testAStockroomOfTheApplicationNamedBesideTheBundleLocksNothing(): void
    {
        $container = $this->load([['stockroom' => 'app.stockroom']]);
        $container->register('app.stockroom');
        $container->register(Stockroom::class);
        $container->register(StockroomTransactions::class);
        $container->register('doctrine.dbal.default_connection');
        $container->setParameter(LibraryPass::STOCKROOM_CONNECTION, 'doctrine.dbal.default_connection');
        new LibraryPass()->process($container);

        self::assertEquals([new Reference('app.stockroom'), null], $container->getDefinition(StockroomLink::class)->getArguments());
        self::assertFalse($container->has(LoanTransaction::class));
    }

    public function testTheBundleStockroomNamedThroughAnAliasKeepsItsTransactions(): void
    {
        $container = $this->load([['stockroom' => 'app.stockroom']]);
        $container->register(Stockroom::class);
        $container->setAlias('app.stockroom', 'app.inner');
        $container->setAlias('app.inner', Stockroom::class);
        $container->register(StockroomTransactions::class);
        new LibraryPass()->process($container);

        self::assertEquals([new Reference('app.stockroom'), new Reference(StockroomTransactions::class)], $container->getDefinition(StockroomLink::class)->getArguments());
    }

    public function testALoopOfAliasesEndsTheirResolution(): void
    {
        $container = $this->load([['stockroom' => 'app.a']]);
        $container->register(Stockroom::class);
        $container->register(StockroomTransactions::class);
        $container->setAlias('app.a', 'app.b');
        $container->setAlias('app.b', 'app.a');
        new LibraryPass()->process($container);

        self::assertEquals([new Reference('app.a'), null], $container->getDefinition(StockroomLink::class)->getArguments());
    }

    public function testHalfTheBundleLinksNothing(): void
    {
        foreach ([Stockroom::class, StockroomTransactions::class] as $only) {
            $container = $this->load([]);
            $container->register($only);
            new LibraryPass()->process($container);

            self::assertSame(NoStockLink::class, (string) $container->getAlias(StockLink::class), $only);
        }
    }

    public function testThePassLeavesOutWhatIsMissing(): void
    {
        $container = $this->load([]);
        new LibraryPass()->process($container);

        self::assertFalse($container->has(MemberResolver::class));
        self::assertSame(NoStockLink::class, (string) $container->getAlias(StockLink::class));
        self::assertFalse($container->hasAlias(ReminderSender::class));
        self::assertFalse($container->hasDefinition(MessengerReminderSender::class));
        self::assertFalse($container->hasDefinition(Reminders::class));
        self::assertFalse($container->hasDefinition(SendRemindersCommand::class));
        self::assertFalse($container->hasDefinition(Pages::class));
        self::assertFalse($container->hasDefinition(CatalogueController::class));
    }

    public function testASenderOfTheApplicationKeepsTheReminders(): void
    {
        $container = $this->load([]);
        $container->register('app.sender');
        $container->setAlias(ReminderSender::class, 'app.sender');
        $container->register('twig');
        new LibraryPass()->process($container);

        self::assertTrue($container->hasDefinition(Reminders::class));
        self::assertFalse($container->hasDefinition(Pages::class), 'forms are missing');
    }

    public function testThePassWithoutParameters(): void
    {
        $container = new ContainerBuilder();
        $container->register(MemberResolver::class);
        new LibraryPass()->process($container);
        new KitSubdirectoryPass()->process($container);

        self::assertFalse($container->hasAlias(StockLink::class));
        self::assertTrue($container->has(MemberResolver::class));
    }

    public function testTheKitIsNamedAfterTheToolkitUnlessConfigured(): void
    {
        $container = $this->load([]);
        $container->setParameter('.ux_toolkit.components.subdirectory', 'ui/kit');
        new KitSubdirectoryPass()->process($container);
        self::assertSame('ui/kit', $container->getDefinition(KitComponents::class)->getArgument(0));

        $bare = $this->load([]);
        new KitSubdirectoryPass()->process($bare);
        self::assertSame('', $bare->getDefinition(KitComponents::class)->getArgument(0));

        $configured = $this->load([['pages' => ['kit_subdirectory' => 'mine']]]);
        $configured->setParameter('.ux_toolkit.components.subdirectory', 'ui');
        new KitSubdirectoryPass()->process($configured);
        self::assertSame('mine', $configured->getDefinition(KitComponents::class)->getArgument(0));

        $odd = $this->load([]);
        $odd->setParameter('.ux_toolkit.components.subdirectory', null);
        new KitSubdirectoryPass()->process($odd);
        self::assertSame('', $odd->getDefinition(KitComponents::class)->getArgument(0));
    }

    public function testTheBundleAddsItsPasses(): void
    {
        $container = new ContainerBuilder();
        new PhilLibraryBundle()->build($container);
        $passes = array_map(static fn (object $pass): string => $pass::class, $container->getCompilerPassConfig()->getBeforeOptimizationPasses());

        self::assertContains(KitSubdirectoryPass::class, $passes);
        self::assertContains(LibraryPass::class, $passes);
    }

    /**
     * @param list<array<array-key, mixed>> $configs
     */
    private function load(array $configs): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $this->extension()->load($configs, $container);

        return $container;
    }

    private function extension(): ExtensionInterface
    {
        $extension = new PhilLibraryBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        return $extension;
    }
}

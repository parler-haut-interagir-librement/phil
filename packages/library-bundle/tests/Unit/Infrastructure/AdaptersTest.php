<?php

declare(strict_types=1);

namespace Phil\LibraryBundle\Tests\Unit\Infrastructure;

use Doctrine\DBAL\DriverManager;
use EasyCorp\Bundle\EasyAdminBundle\Config\Menu\RouteMenuItem;
use Phil\LibraryBundle\Application\Message\SendLoanReminder;
use Phil\LibraryBundle\Domain\Exception\InvalidLibraryData;
use Phil\LibraryBundle\Domain\Status\TitleKind;
use Phil\LibraryBundle\Domain\ValueObject\Record;
use Phil\LibraryBundle\Infrastructure\Doctrine\DbalLoanTransaction;
use Phil\LibraryBundle\Infrastructure\EasyAdmin\LibraryAdmin;
use Phil\LibraryBundle\Infrastructure\Form\TitleData;
use Phil\LibraryBundle\Infrastructure\Http\Pages;
use Phil\LibraryBundle\Infrastructure\Messenger\MessengerReminderSender;
use Phil\LibraryBundle\Infrastructure\Stockroom\NoStockLink;
use Phil\LibraryBundle\Infrastructure\Stockroom\StockroomLink;
use Phil\LibraryBundle\Infrastructure\Twig\KitComponents;
use Phil\LibraryBundle\Infrastructure\Twig\LibraryExtension;
use Phil\Stockroom\Exception\InsufficientStock;
use Phil\Stockroom\Holding;
use Phil\Stockroom\Memory\InMemoryBalances;
use Phil\Stockroom\Memory\InMemoryContainers;
use Phil\Stockroom\Memory\InMemoryItems;
use Phil\Stockroom\Memory\InMemoryJournal;
use Phil\Stockroom\Movement;
use Phil\Stockroom\MovementKind;
use Phil\Stockroom\Stockroom;
use Phil\StockroomBundle\Infrastructure\Dbal\DbalBalances;
use Phil\StockroomBundle\Infrastructure\Dbal\DbalContainers;
use Phil\StockroomBundle\Infrastructure\Dbal\DbalItems;
use Phil\StockroomBundle\Infrastructure\Dbal\DbalJournal;
use Phil\StockroomBundle\Infrastructure\Dbal\StockroomTables;
use Phil\StockroomBundle\Infrastructure\Dbal\StockroomTransactions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecision;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

#[CoversClass(MessengerReminderSender::class)]
#[CoversClass(StockroomLink::class)]
#[CoversClass(DbalLoanTransaction::class)]
#[CoversClass(NoStockLink::class)]
#[CoversClass(KitComponents::class)]
#[CoversClass(LibraryExtension::class)]
#[CoversClass(LibraryAdmin::class)]
#[CoversClass(TitleData::class)]
#[CoversClass(Pages::class)]
final class AdaptersTest extends TestCase
{
    public function testRemindersGoThroughTheBusAsIdentifiers(): void
    {
        $bus = new class implements MessageBusInterface {
            /** @var list<object> */
            public array $messages = [];

            public function dispatch(object $message, array $stamps = []): Envelope
            {
                $this->messages[] = $message;

                return new Envelope($message);
            }
        };
        $sender = new MessengerReminderSender($bus);
        $sender->beforeDue('loan-1', 1440);
        $sender->overdue('loan-1', 2);

        self::assertEquals([new SendLoanReminder('loan-1', SendLoanReminder::DUE, 1440), new SendLoanReminder('loan-1', SendLoanReminder::OVERDUE, 2)], $bus->messages);
    }

    public function testALoanMovesTheItemInTheStockroomUnderTheLoan(): void
    {
        $balances = new InMemoryBalances();
        $journal = new InMemoryJournal();
        $stockroom = new Stockroom(new InMemoryContainers(), new InMemoryItems(), $balances, $journal, new MockClock());
        $stockroom->addContainer('Shelf', id: 'shelf');
        $stockroom->addItem('G-1', 'Azul', home: 'shelf', id: 'item-1');
        $stockroom->receive('item-1', 'shelf');

        $link = new StockroomLink($stockroom);

        $link->out('item-1', 'loan-1');
        self::assertSame([['holder/loan-1', 1]], array_map(static fn (Holding $holding): array => [$holding->place->key(), $holding->quantity], $stockroom->holdings('item-1')));
        $link->back('item-1', 'loan-1');
        self::assertSame([['container/shelf', 1]], array_map(static fn (Holding $holding): array => [$holding->place->key(), $holding->quantity], $stockroom->holdings('item-1')));
        $link->out('item-1', 'loan-2');
        $link->lost('item-1', 'loan-2');
        self::assertSame([], $stockroom->holdings('item-1'));
        self::assertSame([MovementKind::Loss, MovementKind::Out, MovementKind::Return, MovementKind::Out], array_map(static fn (Movement $movement): MovementKind => $movement->kind, \array_slice($stockroom->history('item-1'), 0, 4)));
        self::assertSame(['loan-2', 'loan-2'], [$stockroom->history('item-1')[0]->reference, $stockroom->history('item-1')[1]->reference]);

        $this->expectException(InsufficientStock::class);
        $link->back('item-1', 'loan-2');
    }

    public function testInTheTransactionsOfTheBundleAMoveJoinsTheLoansTransaction(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $tables = new StockroomTables();

        foreach ($tables->definitions($connection->getDatabasePlatform()) as $table) {
            $connection->createSchemaManager()->createTable($table);
        }

        $stockroom = new Stockroom(new DbalContainers($connection, $tables), new DbalItems($connection, $tables), new DbalBalances($connection, $tables), new DbalJournal($connection, $tables), new MockClock());
        $stockroom->addContainer('Shelf', id: 'shelf');
        $stockroom->addItem('G-1', 'Azul', home: 'shelf', id: 'item-1');
        $stockroom->receive('item-1', 'shelf');

        $link = new StockroomLink($stockroom, new StockroomTransactions($connection, $tables));
        $loan = new DbalLoanTransaction($connection);

        try {
            $loan->run(static function () use ($link): never {
                $link->out('item-1', 'loan-1');

                throw new RuntimeException('The loan was not kept.');
            });
        } catch (RuntimeException) {
        }

        self::assertSame([['container/shelf', 1]], array_map(static fn (Holding $holding): array => [$holding->place->key(), $holding->quantity], $stockroom->holdings('item-1')));
        self::assertSame(['receive'], array_map(static fn (Movement $movement): string => MovementKind::In === $movement->kind ? 'receive' : $movement->kind->value, $stockroom->history('item-1')));

        self::assertSame('kept', $loan->run(static function () use ($link): string {
            $link->out('item-1', 'loan-2');

            return 'kept';
        }));
        self::assertFalse($connection->isTransactionActive());
        self::assertSame([['holder/loan-2', 1]], array_map(static fn (Holding $holding): array => [$holding->place->key(), $holding->quantity], $stockroom->holdings('item-1')));
        $link->back('item-1', 'loan-2');
        $link->out('item-1', 'loan-3');
        $link->lost('item-1', 'loan-3');
        self::assertSame([MovementKind::Loss, MovementKind::Out, MovementKind::Return, MovementKind::Out, MovementKind::In], array_map(static fn (Movement $movement): MovementKind => $movement->kind, $stockroom->history('item-1')));
    }

    #[DoesNotPerformAssertions]
    public function testWithoutAStockroomNothingMoves(): void
    {
        $link = new NoStockLink();
        $link->out('item', 'loan');
        $link->back('item', 'loan');
        $link->lost('item', 'loan');
    }

    public function testTheKitIsNamedUnderItsSubdirectory(): void
    {
        self::assertSame('Table:Row', new KitComponents()->name('Table:Row'));
        self::assertSame('ui:Table:Row', new KitComponents('/ui/')->name('Table:Row'));
        self::assertSame('ui:kit:Badge', new KitComponents('ui/kit')->name('Badge'));

        $extension = new LibraryExtension(new KitComponents('ui'), 'America/Toronto');
        self::assertSame(['ui:Badge', 'America/Toronto'], [$extension->kit('Badge'), $extension->timezone()]);
        self::assertSame('UTC', new LibraryExtension(new KitComponents())->timezone());
    }

    public function testTheBackOfficeLinksToThePages(): void
    {
        $items = LibraryAdmin::menuItems();

        self::assertCount(4, $items);
        self::assertContainsOnlyInstancesOf(RouteMenuItem::class, $items);
        self::assertSame(['phil_library_desk', 'phil_library_catalogue', 'phil_library_admin_title_new', 'phil_library_admin_overdue'], array_map(static fn (RouteMenuItem $item): ?string => $item->getAsDto()->getRouteName(), $items));
    }

    public function testTheFormDataMakesARecordAndBack(): void
    {
        $record = new Record(TitleKind::Book, 'Dune', '9780306406157', 'Herbert', 'Chilton', 1965, null, null, null, null, 12, 'en', 'Sand.');
        $data = TitleData::of($record);

        self::assertEquals($record, $data->record());
        self::assertSame([TitleKind::Game, null], [new TitleData()->kind, new TitleData()->name]);

        $this->expectException(InvalidLibraryData::class);
        new TitleData()->record();
    }

    public function testAPageIsRenderedInItsLayoutAndNeverSharedInACache(): void
    {
        $twig = new Environment(new ArrayLoader(['@PhilLibrary/page.html.twig' => '{{ layout }}:{{ x }}']));
        $response = new Pages($twig, 'base.html.twig')->render('page', ['x' => 'y'], Response::HTTP_ACCEPTED);

        self::assertSame(['base.html.twig:y', 202], [$response->getContent(), $response->getStatusCode()]);
        self::assertSame('no-cache, private', $response->headers->get('Cache-Control'));
        self::assertSame('public', new Pages($twig, 'x')->render('page', ['x' => 1], cacheControl: 'public')->headers->get('Cache-Control'));
    }

    public function testAStaffPageAsksForItsRole(): void
    {
        $checker = new class implements AuthorizationCheckerInterface {
            public function isGranted(mixed $attribute, mixed $subject = null, ?AccessDecision $accessDecision = null): bool
            {
                return 'ROLE_LIBRARIAN' === $attribute;
            }
        };
        $twig = new Environment(new ArrayLoader([]));
        new Pages($twig, 'x', $checker)->denyUnlessGranted('ROLE_LIBRARIAN');

        try {
            new Pages($twig, 'x', $checker)->denyUnlessGranted('ROLE_ADMIN');
            self::fail('A role missing was granted.');
        } catch (AccessDeniedException $accessDeniedException) {
            self::assertSame(['ROLE_ADMIN'], $accessDeniedException->getAttributes());
        }

        $this->expectException(AccessDeniedException::class);
        new Pages($twig, 'x')->denyUnlessGranted('ROLE_LIBRARIAN');
    }
}

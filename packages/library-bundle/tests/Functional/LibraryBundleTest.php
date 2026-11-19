<?php

declare(strict_types=1);

namespace Phil\LibraryBundle\Tests\Functional;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use LogicException;
use Override;
use Phil\LibraryBundle\Application\Catalogue;
use Phil\LibraryBundle\Application\Loans;
use Phil\LibraryBundle\Application\Message\SendLoanReminder;
use Phil\LibraryBundle\Domain\Model\Loan;
use Phil\LibraryBundle\Domain\Model\Title;
use Phil\LibraryBundle\Domain\Status\CopyCondition;
use Phil\LibraryBundle\Domain\Status\LoanStatus;
use Phil\LibraryBundle\Domain\Status\TitleKind;
use Phil\LibraryBundle\Domain\ValueObject\Record;
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
use Phil\LibraryBundle\Tests\Functional\App\ReminderHandler;
use Phil\LibraryBundle\Tests\Functional\App\TestKernel;
use Phil\Stockroom\Exception\InsufficientStock;
use Phil\Stockroom\Holding;
use Phil\Stockroom\Stockroom;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DomCrawler\Crawler;
use Throwable;

/**
 * The pages and the commands in an application: the catalogue searched and paged by links, a title and its copies, the
 * desk lending and taking back, the administration adding a title and a copy and listing the loans overdue, the
 * member's loans, and the reminders handled through Messenger — on SQLite, with the recipes of the PHIL UX kit.
 */
#[CoversClass(CatalogueController::class)]
#[CoversClass(DeskController::class)]
#[CoversClass(AdminController::class)]
#[CoversClass(MemberLoansController::class)]
#[CoversClass(SearchType::class)]
#[CoversClass(LendType::class)]
#[CoversClass(ReturnType::class)]
#[CoversClass(TitleType::class)]
#[CoversClass(CopyType::class)]
#[CoversClass(DbalTitleSearch::class)]
#[CoversClass(DoctrineTitleRepository::class)]
#[CoversClass(DoctrineCopyRepository::class)]
#[CoversClass(DoctrineLoanRepository::class)]
final class LibraryBundleTest extends WebTestCase
{
    private KernelBrowser $client;

    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    #[Override]
    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->client->disableReboot();

        $entityManager = $this->service('doctrine.orm.entity_manager', EntityManagerInterface::class);
        $tool = new SchemaTool($entityManager);
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    public function testTheCatalogueIsSearchedAndPagedWithoutJavaScript(): void
    {
        $azul = $this->shelve('Azul', ['G-1', 'G-2'], 2, 4, 'Michael Kiesling');
        $this->shelve('Catan', ['G-3'], 3, 4);
        $this->shelve('Patchwork', ['G-4'], 2, 2);
        $this->shelve('100% Orange', [], 1, 6);
        $this->service(Loans::class, Loans::class)->lend('G-3', 'card-7');

        $page = $this->client->request('GET', '/library/');
        self::assertResponseIsSuccessful();
        self::assertSame('no-cache, private', $this->client->getResponse()->headers->get('Cache-Control'));
        self::assertSame('4 titles found.', $page->filter('main p')->first()->text());
        self::assertSame(['100% Orange', 'Azul'], $page->filter('td a')->each(static fn (Crawler $link): string => $link->text()));
        self::assertSame(['0 of 0 on the shelf', '2 of 2 on the shelf'], $page->filter('.isBadge')->each(static fn (Crawler $badge): string => $badge->text()));
        self::assertSame('/library/?page=2', $page->filter('a[rel=next]')->attr('href'));
        self::assertCount(0, $page->filter('a[rel=prev]'));
        self::assertSame('Page 1 of 2', $page->filter('nav span')->text());

        $second = $this->client->request('GET', '/library/?page=2');
        self::assertSame(['Catan', 'Patchwork'], $second->filter('td a')->each(static fn (Crawler $link): string => $link->text()));
        self::assertSame('/library/?page=1', $second->filter('a[rel=prev]')->attr('href'));
        self::assertSame(['0 of 1 on the shelf', '1 of 1 on the shelf'], $second->filter('.isBadge')->each(static fn (Crawler $badge): string => $badge->text()));

        $filtered = $this->client->request('GET', '/library/?q=kiesling&kind=game&players=3&available=1&page=1');
        self::assertSame(['Azul'], $filtered->filter('td a')->each(static fn (Crawler $link): string => $link->text()));
        self::assertSame('/library/titles/'.$azul, $filtered->filter('td a')->attr('href'));
        self::assertSame('One title found.', $filtered->filter('main p')->first()->text());

        self::assertSame(['Azul', 'Patchwork'], $this->client->request('GET', '/library/?players=2&available=1&q=')->filter('td a')->each(static fn (Crawler $link): string => $link->text()), 'Catan is out and needs three');
        self::assertSame(['100% Orange'], $this->client->request('GET', '/library/?q=100%25')->filter('td a')->each(static fn (Crawler $link): string => $link->text()), 'a percent sign is literal');
        self::assertSame('No title found.', $this->client->request('GET', '/library/?q=_')->filter('main p')->first()->text(), 'an underscore is literal');
        self::assertSame(['100% Orange', 'Azul'], $this->client->request('GET', '/library/?page=nope')->filter('td a')->each(static fn (Crawler $link): string => $link->text()));
        self::assertSame(['100% Orange', 'Azul'], $this->client->request('GET', '/library/?page=1001')->filter('td a')->each(static fn (Crawler $link): string => $link->text()), 'past the last page read, the first');
        self::assertSame(['100% Orange', 'Azul'], $this->client->request('GET', '/library/?page=02')->filter('td a')->each(static fn (Crawler $link): string => $link->text()));
        self::assertSame([], $this->client->request('GET', '/library/?page=1000')->filter('td a')->each(static fn (Crawler $link): string => $link->text()));
        self::assertSame([], $this->client->request('GET', '/library/?page=999')->filter('td a')->each(static fn (Crawler $link): string => $link->text()));

        $this->client->request('GET', '/library/?players=0');
        self::assertResponseStatusCodeSame(422);
    }

    public function testATitleShowsItsRecordAndWhereEachCopyIs(): void
    {
        $id = $this->shelve('Azul', ['G-1', 'G-2', 'G-3'], 2, 4, 'Michael Kiesling');
        $loans = $this->service(Loans::class, Loans::class);
        $catalogue = $this->service(Catalogue::class, Catalogue::class);
        $loans->lend('G-1', 'card-7');
        $catalogue->assess($catalogue->copyByBarcode('G-3')->id, CopyCondition::Withdrawn, 'Burnt.');

        $page = $this->client->request('GET', '/library/titles/'.$id);

        self::assertResponseIsSuccessful();
        self::assertSame('Azul', $page->filter('h1')->text());
        self::assertStringContainsString('Game — Michael Kiesling', $page->filter('.isCard')->text());
        self::assertSame(['Out until 2026-11-27', 'On the shelf', 'Withdrawn'], $page->filter('td .isBadge')->each(static fn (Crawler $badge): string => $badge->text()));
        self::assertStringContainsString('Withdrawn — Burnt.', $page->filter('tbody')->text());

        $this->clock()->modify('+20 days');
        self::assertSame('Overdue since 2026-11-27', $this->client->request('GET', '/library/titles/'.$id)->filter('td .isBadge')->first()->text());

        $this->client->request('GET', '/library/titles/01a1d417-7260-75f5-8070-9337bfaf98af');
        self::assertResponseStatusCodeSame(404);
    }

    public function testTheDeskLendsAndTakesBack(): void
    {
        $this->shelve('Azul', ['G-1'], 2, 4);

        $this->client->request('GET', '/library/desk');
        self::assertResponseStatusCodeSame(403);

        $this->client->setServerParameter('HTTP_X_ROLE', 'ROLE_LIBRARIAN');
        $page = $this->client->request('GET', '/library/desk');
        self::assertResponseIsSuccessful();

        $lent = $this->client->submit($page->selectButton('Lend')->form(['lend[barcode]' => 'G-1', 'lend[member]' => 'card-7']));
        self::assertResponseIsSuccessful();
        self::assertSame('Azul (G-1) is lent until 2026-11-27.', trim($lent->filter('.isAlert')->text()));

        $again = $this->client->submit($lent->selectButton('Lend')->form(['lend[barcode]' => 'G-1', 'lend[member]' => 'card-8']));
        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('Another loan keeps this copy over that span.', $again->filter('main')->text());

        $address = $this->client->submit($again->selectButton('Lend')->form(['lend[barcode]' => 'G-1', 'lend[member]' => 'jo@example.com']));
        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('never by an e-mail address', $address->filter('main')->text());

        $unknown = $this->client->submit($address->selectButton('Lend')->form(['lend[barcode]' => 'G-9', 'lend[member]' => 'card-8']));
        self::assertStringContainsString('There is no copy "G-9".', $unknown->filter('main')->text());

        $back = $this->client->submit($unknown->selectButton('Take back')->form(['return[barcode]' => 'G-1', 'return[condition]' => 'incomplete', 'return[note]' => 'Two tiles missing.']));
        self::assertResponseIsSuccessful();
        self::assertSame('Azul (G-1) is back.', trim($back->filter('.isAlert')->text()));
        $copy = $this->service(Catalogue::class, Catalogue::class)->copyByBarcode('G-1');
        self::assertSame([CopyCondition::Incomplete, 'Two tiles missing.'], [$copy->condition(), $copy->note()]);

        $twice = $this->client->submit($back->selectButton('Take back')->form(['return[barcode]' => 'G-1']));
        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('The copy "G-1" is not out.', $twice->filter('main')->text());

        $this->client->request('POST', '/library/desk', ['lend' => ['barcode' => 'G-1', 'member' => 'card-7']]);
        self::assertResponseStatusCodeSame(422, 'without its token, nothing is lent');
    }

    public function testTheAdministrationAddsTitlesAndCopies(): void
    {
        $this->client->request('GET', '/library/admin/titles/new');
        self::assertResponseStatusCodeSame(403);

        $this->client->setServerParameter('HTTP_X_ROLE', 'ROLE_LIBRARIAN');
        $form = $this->client->request('GET', '/library/admin/titles/new')->selectButton('Save')->form();
        $this->client->submit($form, ['title[kind]' => 'game', 'title[name]' => 'Azul', 'title[isbn]' => '0-306-40615-2', 'title[minPlayers]' => '2', 'title[maxPlayers]' => '4']);
        self::assertResponseStatusCodeSame(303);
        $location = (string) $this->client->getResponse()->headers->get('Location');
        self::assertMatchesRegularExpression('#^/library/titles/[0-9a-f-]{36}$#', $location);
        $id = substr($location, \strlen('/library/titles/'));
        self::assertSame('9780306406157', $this->service(Catalogue::class, Catalogue::class)->title($id)->record()->isbn);

        $wrong = $this->client->submit($this->client->request('GET', '/library/admin/titles/'.$id)->selectButton('Save')->form(), ['title[minPlayers]' => '5']);
        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('5 is above 4', $wrong->filter('main')->text());

        $edit = $this->client->request('GET', '/library/admin/titles/'.$id);
        self::assertSame('Azul', $edit->filter('input[name="title[name]"]')->attr('value'));
        $this->client->submit($edit->selectButton('Save')->form(), ['title[name]' => 'Azul: Summer Pavilion']);
        self::assertResponseStatusCodeSame(303);
        self::assertSame('Azul: Summer Pavilion', $this->service(Catalogue::class, Catalogue::class)->title($id)->name());

        $copyForm = $this->client->request('GET', '/library/admin/titles/'.$id.'/copies/new')->selectButton('Save')->form();
        $this->client->submit($copyForm, ['copy[barcode]' => 'G-1', 'copy[condition]' => 'worn', 'copy[stockroomItem]' => ' item-1 ']);
        self::assertResponseStatusCodeSame(303);
        self::assertSame('/library/titles/'.$id, $this->client->getResponse()->headers->get('Location'));
        $copy = $this->service(Catalogue::class, Catalogue::class)->copyByBarcode('G-1');
        self::assertSame([CopyCondition::Worn, 'item-1'], [$copy->condition(), $copy->stockroomItem()]);

        $taken = $this->client->submit($this->client->request('GET', '/library/admin/titles/'.$id.'/copies/new')->selectButton('Save')->form(), ['copy[barcode]' => 'G-1', 'copy[stockroomItem]' => '']);
        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('The barcode "G-1" is taken.', $taken->filter('main')->text());

        $this->client->request('GET', '/library/admin/titles/01a1d417-7260-75f5-8070-9337bfaf98af');
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/library/admin/titles/01a1d417-7260-75f5-8070-9337bfaf98af/copies/new');
        self::assertResponseStatusCodeSame(404);
    }

    public function testTheLoansOverdueAreListedForTheStaff(): void
    {
        $this->shelve('Azul', ['G-1', 'G-2'], 2, 4);
        $loans = $this->service(Loans::class, Loans::class);
        $loans->lend('G-1', 'card-7');
        $loans->lend('G-2', 'card-8', new DateTimeImmutable('2026-12-08 12:00', new DateTimeZone('America/Toronto')));
        $this->clock()->modify('+20 days');

        $this->client->request('GET', '/library/admin/overdue');
        self::assertResponseStatusCodeSame(403);

        $this->client->setServerParameter('HTTP_X_ROLE', 'ROLE_LIBRARIAN');
        $page = $this->client->request('GET', '/library/admin/overdue');
        self::assertResponseIsSuccessful();
        self::assertSame(['card-7'], $page->filter('tbody tr')->each(static fn (Crawler $row): string => $row->filter('td')->eq(2)->text()));
        self::assertSame('Overdue', $page->filter('tbody .isBadge')->text());

        $loans->giveBack('G-1');
        self::assertSame('No loan is overdue.', $this->client->request('GET', '/library/admin/overdue')->filter('main p')->text());
    }

    public function testAMemberSeesTheirLoans(): void
    {
        $this->shelve('Azul', ['G-1', 'G-2'], 2, 4);
        $loans = $this->service(Loans::class, Loans::class);
        $loans->lend('G-1', 'card-7');
        $loans->giveBack('G-1');
        $loans->lend('G-2', 'card-7');
        $loans->lend('G-1', 'card-8');

        $this->client->request('GET', '/library/my-loans');
        self::assertResponseStatusCodeSame(403);

        $this->client->setServerParameter('HTTP_X_MEMBER', 'card-7');
        $page = $this->client->request('GET', '/library/my-loans');
        self::assertResponseIsSuccessful();
        self::assertSame(['G-2', 'G-1'], $page->filter('tbody tr')->each(static fn (Crawler $row): string => $row->filter('td')->eq(1)->text()));
        self::assertSame(['Out', 'Returned'], $page->filter('tbody .isBadge')->each(static fn (Crawler $badge): string => $badge->text()));

        $this->client->setServerParameter('HTTP_X_MEMBER', 'card-9');
        self::assertSame('You have no loan.', $this->client->request('GET', '/library/my-loans')->filter('main p')->text());
    }

    public function testTheRemindersAreHandledThroughMessenger(): void
    {
        $this->shelve('Azul', ['G-1'], 2, 4);
        $loan = $this->service(Loans::class, Loans::class)->lend('G-1', 'card-7');
        $this->clock()->modify('2026-11-27 08:00');
        $application = new Application(self::$kernel ?? throw new LogicException('No kernel.'));

        $send = new CommandTester($application->find('phil:library:send-reminders'));
        self::assertSame(0, $send->execute([]));
        self::assertSame("1 reminders sent.\n", $send->getDisplay());
        self::assertEquals([new SendLoanReminder($loan->id, SendLoanReminder::DUE, 1440)], $this->service(ReminderHandler::class, ReminderHandler::class)->messages);

        $forget = new CommandTester($application->find('phil:library:forget-history'));
        self::assertSame(0, $forget->execute([]));
        self::assertSame("0 loans forgot their member.\n", $forget->getDisplay(), 'the history is kept');
    }

    public function testALinkedCopyMovesItsItemInTheStockroomOfTheBundleWithTheLoan(): void
    {
        $stockroom = $this->service(Stockroom::class, Stockroom::class);
        $stockroom->addContainer('Shelf', id: 'shelf');
        $stockroom->addItem('G-1', 'Azul', home: 'shelf', id: 'item-1');
        $stockroom->receive('item-1', 'shelf');

        $title = $this->shelve('Azul', [], 2, 4);
        $this->service(Catalogue::class, Catalogue::class)->addCopy($title, 'G-1', stockroomItem: 'item-1');
        $loans = $this->service(Loans::class, Loans::class);

        $loan = $loans->lend('G-1', 'card-7');
        $connection = $this->service('doctrine.dbal.default_connection', Connection::class);
        self::assertSame([['holder/'.$loan->id, 1]], array_map(static fn (Holding $holding): array => [$holding->place->key(), $holding->quantity], $stockroom->holdings('item-1')));
        self::assertEquals(1, $connection->fetchOne('SELECT COUNT(*) FROM phil_stockroom_movement WHERE kind = ? AND reference = ?', ['out', $loan->id]));

        $loans->giveBack('G-1');
        self::assertSame([['container/shelf', 1]], array_map(static fn (Holding $holding): array => [$holding->place->key(), $holding->quantity], $stockroom->holdings('item-1')));

        $connection->executeStatement('DELETE FROM phil_stockroom_balance');

        try {
            $loans->lend('G-1', 'card-8');
            self::fail('A copy whose item is not in the stockroom was lent.');
        } catch (Throwable $throwable) {
            self::assertInstanceOf(InsufficientStock::class, $throwable);
        }

        self::assertSame([], $this->service(DoctrineLoanRepository::class, DoctrineLoanRepository::class)->ofMember('card-8', 10, false));
        self::assertFalse($connection->isTransactionActive());
    }

    public function testTheRepositoriesOnSqlite(): void
    {
        $id = $this->shelve('Azul', ['G-1', 'G-2'], 2, 4);
        $loans = $this->service(Loans::class, Loans::class);
        $repository = $this->service(DoctrineLoanRepository::class, DoctrineLoanRepository::class);
        $first = $loans->lend('G-1', 'card-7');
        $reserved = $loans->reserve($this->service(Catalogue::class, Catalogue::class)->copyByBarcode('G-2')->id, 'card-7', new DateTimeImmutable('2026-11-20 10:00'), new DateTimeImmutable('2026-11-22 10:00'));

        self::assertSame(2, $repository->countOpen('card-7'));
        self::assertFalse($repository->hasOverdue('card-7', $this->clock()->now()));
        self::assertTrue($repository->hasOverdue('card-7', $this->clock()->now()->modify('+15 days')));
        self::assertSame([$reserved->id, $first->id], array_map(static fn (Loan $loan): string => $loan->id, $repository->ofMember('card-7', 10)));
        self::assertSame(1, $repository->forgetMember('card-7') - 1);
        $read = $repository->get($first->id);
        self::assertInstanceOf(Loan::class, $read);
        self::assertSame([LoanStatus::Active, null], [$read->status(), $read->member()]);
        self::assertInstanceOf(Title::class, $this->service(DoctrineTitleRepository::class, DoctrineTitleRepository::class)->get($id));
    }

    /**
     * @param list<string> $barcodes
     */
    private function shelve(string $name, array $barcodes, int $min, int $max, ?string $creators = null): string
    {
        $catalogue = $this->service(Catalogue::class, Catalogue::class);
        $title = $catalogue->add(new Record(TitleKind::Game, $name, creators: $creators, minPlayers: $min, maxPlayers: $max));

        foreach ($barcodes as $barcode) {
            $catalogue->addCopy($title->id, $barcode);
        }

        return $title->id;
    }

    private function clock(): MockClock
    {
        return $this->service('clock', MockClock::class);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function service(string $id, string $class): object
    {
        $service = self::getContainer()->get($id);
        self::assertInstanceOf($class, $service);

        return $service;
    }
}

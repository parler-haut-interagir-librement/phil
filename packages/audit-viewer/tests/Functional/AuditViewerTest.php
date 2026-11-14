<?php

declare(strict_types=1);

namespace Phil\AuditViewer\Tests\Functional;

use Doctrine\DBAL\Connection;
use Override;
use Phil\AuditBundle\Application\AuditTrail;
use Phil\AuditBundle\Domain\Chain\Facts;
use Phil\AuditBundle\Domain\Chain\Subject;
use Phil\AuditBundle\Domain\Contract\AuditRowRepository;
use Phil\AuditBundle\Infrastructure\Doctrine\AuditTable;
use Phil\AuditViewer\Infrastructure\EasyAdmin\AuditHistoryActions;
use Phil\AuditViewer\Tests\Functional\App\Auditors;
use Phil\AuditViewer\Tests\Functional\App\JsonSerializer;
use Phil\AuditViewer\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Routing\RouterInterface;

/**
 * The pages of the viewer in an EasyAdmin back office, followed as an auditor reads them: the log and its filters,
 * a row, the history of a record and the action that leads to it from a CRUD page, the chain by site and the
 * verification of a segment, and an export that lands in the trail.
 */
#[CoversClass(AuditHistoryActions::class)]
final class AuditViewerTest extends WebTestCase
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

        $connection = $this->service('doctrine.dbal.default_connection', Connection::class);
        $schema = $connection->createSchemaManager();

        foreach ([AuditTable::NAME, AuditTable::FEED, 'shop_order', 'shop_note'] as $table) {
            if ($schema->tablesExist([$table])) {
                $connection->executeStatement('DROP TABLE '.$table);
            }
        }

        foreach (AuditTable::configure($schema->introspectSchema(), $connection->getDatabasePlatform())->toSql($connection->getDatabasePlatform()) as $sql) {
            $connection->executeStatement($sql);
        }

        $connection->executeStatement('CREATE TABLE shop_order (id VARCHAR(16) NOT NULL PRIMARY KEY, label VARCHAR(64) NOT NULL)');
        $connection->executeStatement('CREATE TABLE shop_note (id VARCHAR(16) NOT NULL PRIMARY KEY)');
        $connection->insert('shop_order', ['id' => '42', 'label' => 'Order 42']);
        $connection->insert('shop_note', ['id' => 'n-1']);

        $clock = $this->service('clock', MockClock::class);
        $clock->modify('2026-11-08 10:00:00 UTC');

        $trail = $this->service(AuditTrail::class, AuditTrail::class);
        $trail->record('orders', ['total' => '10.00'], Facts::of('order.created', 'acct-7', Subject::of('order', '42')));

        $clock->modify('+1 minute');
        $trail->record('orders', ['from' => 'cart', 'to' => 'paid'], Facts::of('workflow.order.pay', 'acct-7', Subject::of('order', '42')));
        $clock->modify('+1 minute');
        $trail->record('members', ['plan' => 'basic'], Facts::of('member.joined', 'acct-8', Subject::of('member', 'm-1')));
        $clock->modify('+1 hour');
        $trail->record('orders', ['from' => 'paid', 'to' => 'shipped'], Facts::of('workflow.order.ship', 'acct-9', Subject::of('order', '42')));
    }

    public function testOnlyTheViewersRoleReadsTheLog(): void
    {
        $this->client->request('GET', '/admin/audit');
        self::assertResponseStatusCodeSame(401);

        $this->client->loginUser(Auditors::account('member'));
        $this->client->request('GET', '/admin/audit');
        self::assertResponseStatusCodeSame(403);

        foreach (['/admin/audit/chain', '/admin/audit/chain/orders', '/admin/audit/row/orders/1', '/admin/audit/history/order/42'] as $page) {
            $this->client->request('GET', $page);
            self::assertResponseStatusCodeSame(403, $page);
        }
    }

    public function testTheLogIsFilteredAndPaged(): void
    {
        $this->client->loginUser(Auditors::account('reader'));
        $crawler = $this->client->request('GET', '/admin/audit');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('no-store', (string) $this->client->getResponse()->headers->get('Cache-Control'));
        self::assertSelectorTextContains('h1', 'Audit log');
        self::assertSame(['workflow.order.ship', 'member.joined'], $crawler->filter('tbody tr td:nth-child(3)')->each(static fn (Crawler $cell): string => trim($cell->text())));
        self::assertSame('2026-11-08 05:02:00', trim($crawler->filter('tbody tr')->eq(1)->filter('time')->text()), 'Shown in the time zone of the viewer.');
        self::assertSame('2026-11-08T10:02:00.000000Z', $crawler->filter('tbody tr')->eq(1)->filter('time')->attr('datetime'));
        self::assertCount(0, $crawler->filter('form[method=post]'), 'No export without its role.');
        self::assertSame('/admin/audit/row/orders/3', $crawler->filter('tbody tr td:nth-child(2) a')->first()->attr('href'));
        self::assertSame('/admin/audit/history/order/42', $crawler->filter('tbody tr td:nth-child(5) a')->first()->attr('href'));

        $next = $crawler->filter('a[rel=next]')->link();
        $crawler = $this->client->click($next);
        self::assertSame(['workflow.order.pay', 'order.created'], $crawler->filter('tbody tr td:nth-child(3)')->each(static fn (Crawler $cell): string => trim($cell->text())));
        self::assertCount(0, $crawler->filter('a[rel=next]'));

        $crawler = $this->client->request('GET', '/admin/audit', ['action' => 'workflow.order.', 'prefix' => '1']);
        self::assertSame(['workflow.order.ship', 'workflow.order.pay'], $crawler->filter('tbody tr td:nth-child(3)')->each(static fn (Crawler $cell): string => trim($cell->text())));
        self::assertNotNull($crawler->filter('#phil-audit-prefix')->attr('checked'));
        self::assertSame('workflow.order.', $crawler->filter('#phil-audit-action')->attr('value'));
        self::assertCount(0, $crawler->filter('a[rel=next]'), 'Exactly a page: no next one.');

        $crawler = $this->client->request('GET', '/admin/audit', ['segment' => 'orders']);
        self::assertStringContainsString('segment=orders', (string) $crawler->filter('a[rel=next]')->attr('href'), 'The next page keeps the filters.');
        $crawler = $this->client->click($crawler->filter('a[rel=next]')->link());
        self::assertSame(['order.created'], $crawler->filter('tbody tr td:nth-child(3)')->each(static fn (Crawler $cell): string => trim($cell->text())));

        $crawler = $this->client->request('GET', '/admin/audit', ['actor' => 'acct-8']);
        self::assertSame(['member.joined'], $crawler->filter('tbody tr td:nth-child(3)')->each(static fn (Crawler $cell): string => trim($cell->text())));

        $crawler = $this->client->request('GET', '/admin/audit', ['subject_type' => 'member']);
        self::assertSame(['member.joined'], $crawler->filter('tbody tr td:nth-child(3)')->each(static fn (Crawler $cell): string => trim($cell->text())));

        $this->client->request('GET', '/admin/audit', ['segment' => 'orders', 'since' => '2026-11-08 05:01', 'until' => '2026-11-08T06:30']);
        self::assertResponseStatusCodeSame(400);
        self::assertSelectorTextContains('.alert-danger', 'These filters do not read: From (America/Toronto).');

        $crawler = $this->client->request('GET', '/admin/audit', ['segment' => 'orders', 'since' => '2026-11-08T05:01', 'until' => '2026-11-08T06:00']);
        self::assertResponseIsSuccessful();
        self::assertSame(['workflow.order.pay'], $crawler->filter('tbody tr td:nth-child(3)')->each(static fn (Crawler $cell): string => trim($cell->text())));

        $crawler = $this->client->request('GET', '/admin/audit', ['actor' => 'ada@example.org', 'subject_id' => '42', 'cursor' => 'nonsense']);
        self::assertResponseStatusCodeSame(400);
        self::assertSelectorTextContains('.alert-danger', 'These filters do not read: Actor, Record identifier, Page.');
        self::assertCount(0, $crawler->filter('table'));
        self::assertSame('is-invalid', substr((string) $crawler->filter('#phil-audit-actor')->attr('class'), -10));

        $this->client->request('GET', '/admin/audit', ['actor' => 'nobody']);
        self::assertSelectorTextContains('tbody', 'No row matches these filters.');
    }

    public function testARowAndTheHistoryOfItsRecord(): void
    {
        $this->client->loginUser(Auditors::account('reader'));
        $crawler = $this->client->request('GET', '/admin/audit/row/orders/2');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Row orders #2');
        self::assertSelectorNotExists('.alert-danger');
        self::assertSame(['from', 'to'], $crawler->filter('dl.mb-0 dt')->each(static fn (Crawler $term): string => trim($term->text())));
        self::assertSame(['cart', 'paid'], $crawler->filter('dl.mb-0 dd')->each(static fn (Crawler $term): string => trim($term->text())));
        self::assertSelectorTextContains('dl.row', 'acct-7');
        self::assertSelectorTextContains('dl.row', 'json-1');

        $this->client->request('GET', '/admin/audit/row/orders/99');
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/admin/audit/row/Orders/1');
        self::assertResponseStatusCodeSame(404);

        $crawler = $this->client->request('GET', '/admin/audit/history/order/42');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'History of order 42');
        self::assertSame(['order.created', 'workflow.order.pay'], $crawler->filter('ol h2 a')->each(static fn (Crawler $link): string => trim($link->text())));
        self::assertStringContainsString('by acct-7', $crawler->filter('ol h2')->first()->text());
        $crawler = $this->client->click($crawler->filter('a[rel=next]')->link());
        self::assertSame(['workflow.order.ship'], $crawler->filter('ol h2 a')->each(static fn (Crawler $link): string => trim($link->text())));
        self::assertSelectorTextContains('ol dl', 'shipped');

        $this->client->request('GET', '/admin/audit/history/order/42', ['cursor' => 'nonsense']);
        self::assertResponseStatusCodeSame(400);

        $this->client->request('GET', '/admin/audit/history/order/77');
        self::assertSelectorTextContains('body', 'Nothing was recorded about this record.');
    }

    public function testTheChainIsVerifiedSegmentBySegment(): void
    {
        $this->client->loginUser(Auditors::account('reader'));
        $crawler = $this->client->request('GET', '/admin/audit/chain');

        self::assertResponseIsSuccessful();
        self::assertSame(['members', 'orders'], $crawler->filter('tbody tr td:first-child')->each(static fn (Crawler $cell): string => trim($cell->text())));

        self::assertSame(['Verify', 'Verify'], $crawler->filter('tbody tr td:last-child a')->each(static fn (Crawler $link): string => trim($link->text())));
        $this->client->click($crawler->filter('a[href="/admin/audit/chain/orders"]')->link());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.alert-success', 'The segment holds');

        $this->service('doctrine.dbal.default_connection', Connection::class)->update(AuditTable::NAME, ['payload' => '{"total":"1.00"}'], ['segment' => 'orders', 'sequence' => 1]);
        $crawler = $this->client->request('GET', '/admin/audit/chain/orders');
        self::assertSelectorTextContains('.alert-danger', 'The segment does not hold: 1 break(s) found.');
        self::assertSame('/admin/audit/row/orders/1', $crawler->filter('.alert-danger + ul li a')->attr('href'));
        self::assertSelectorTextContains('.alert-danger + ul li', 'the row was changed after it was written');
        self::assertSelectorTextContains('.alert-danger + ul li .badge', 'alarm');

        $this->client->request('GET', '/admin/audit/row/orders/1');
        self::assertSelectorTextContains('.alert-danger', 'This row does not match its hash');

        $this->client->request('GET', '/admin/audit/chain/payments');
        self::assertResponseStatusCodeSame(404);
    }

    public function testAnExportIsRecordedThenWritten(): void
    {
        $this->client->loginUser(Auditors::account('reader'));
        $this->client->request('POST', '/admin/audit/export');
        self::assertResponseStatusCodeSame(403);

        $this->client->loginUser(Auditors::account('exporter'));
        $crawler = $this->client->request('GET', '/admin/audit', ['action' => 'workflow.order.', 'prefix' => '1']);
        $form = $crawler->selectButton('Export as CSV')->form();
        self::assertSame(['action' => 'workflow.order.', 'prefix' => '1'], array_diff_key($form->getValues(), ['_token' => true]));

        $this->client->request('POST', '/admin/audit/export', ['action' => 'workflow.order.', '_token' => 'forged']);
        self::assertResponseStatusCodeSame(400);

        $this->client->submit($form);
        $csv = $this->client->getInternalResponse()->getContent();
        $response = $this->client->getResponse();

        self::assertResponseIsSuccessful();
        self::assertSame('text/csv; charset=UTF-8', $response->headers->get('Content-Type'));
        self::assertSame('attachment; filename=audit-20261108-110200.csv', $response->headers->get('Content-Disposition'));
        self::assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $lines = explode("\r\n", trim($csv));
        self::assertSame('recorded_at,segment,sequence,kind,action,actor,subject_type,subject_id,tenant,serialization_version,payload,previous_hash,hash,intact', $lines[0]);
        self::assertCount(3, $lines);
        self::assertStringStartsWith('2026-11-08T11:02:00.000000Z,orders,3,event,workflow.order.ship,acct-9,order,42,,json-1,', $lines[1]);
        self::assertStringEndsWith(',yes', $lines[2]);

        $journal = $this->service('doctrine.dbal.default_connection', Connection::class)->fetchAssociative('SELECT action, actor, payload FROM '.AuditTable::NAME." WHERE segment = 'audit'");
        self::assertSame(['action' => 'audit.export', 'actor' => 'acct-exporter', 'payload' => '{"filter_action":"workflow.order.","filter_prefix":"1","format":"csv","limit":3}'], $journal);

        $this->client->request('POST', '/admin/audit/export', ['_token' => $form->getValues()['_token']]);
        $all = explode("\r\n", trim($this->client->getInternalResponse()->getContent()));
        self::assertCount(4, $all, 'At most export_limit rows.');
        self::assertStringStartsWith('2026-11-08T11:02:00.000000Z,orders,3,', $all[1]);
        self::assertStringContainsString(',audit,2,event,audit.export,acct-exporter,', $all[2], 'An export is recorded before it reads the log.');
        self::assertStringContainsString(',audit,1,event,audit.export,acct-exporter,', $all[3]);

        $this->client->request('POST', '/admin/audit/export', ['_token' => $form->getValues()['_token'], 'since' => 'yesterday']);
        self::assertResponseStatusCodeSame(400);
    }

    public function testTheHistoryActionLinksAnAuditedRecordToItsHistory(): void
    {
        $router = $this->service('router', RouterInterface::class);
        $detail = $router->generate('admin_shop_order_detail', ['entityId' => '42']);
        $index = $router->generate('admin_shop_order_index');

        $this->client->loginUser(Auditors::account('clerk'));
        $crawler = $this->client->request('GET', $detail);
        self::assertResponseIsSuccessful();
        $link = $crawler->filter('a.action-phil_audit_history');
        self::assertCount(1, $link);
        self::assertSame('/admin/audit/history/order/42', $link->attr('href'));
        self::assertSame('History', trim($link->text()));

        $crawler = $this->client->request('GET', $index);
        self::assertSame(['/changes'], $crawler->filter('a.action-phil_audit_history')->each(static fn (Crawler $action): string => (string) $action->attr('href')), 'The controller keeps the action it named so.');

        $crawler = $this->client->click($link->link());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'History of order 42');
        self::assertSame([], $crawler->filter('ol h2 a')->each(static fn (Crawler $row): string => $row->text()), 'A clerk reads the history, not the rows of the log.');
        self::assertStringContainsString('order.created', $crawler->filter('ol h2')->first()->text());
        self::assertCount(0, $crawler->filter('a[href^="/admin/audit?"]'));

        $this->client->request('GET', '/admin/audit');
        self::assertResponseStatusCodeSame(403, 'The history is not the log.');
        $this->client->request('GET', '/admin/audit/row/orders/1');
        self::assertResponseStatusCodeSame(403);

        $crawler = $this->client->request('GET', $router->generate('admin_note_detail', ['entityId' => 'n-1']));
        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('a.action-phil_audit_history'), 'A note is not audited.');

        $this->client->loginUser(Auditors::account('member'));
        $crawler = $this->client->request('GET', $detail);
        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('a.action-phil_audit_history'), 'Shown only to whoever may read the history.');

        $this->client->loginUser(Auditors::account('reader'));
        $crawler = $this->client->click($this->client->request('GET', $detail)->filter('a.action-phil_audit_history')->link());
        self::assertSame(['order.created', 'workflow.order.pay'], $crawler->filter('ol h2 a')->each(static fn (Crawler $row): string => trim($row->text())), 'A reader of the log follows each row.');
        self::assertCount(1, $crawler->filter('a[href="/admin/audit?subject_type=order&subject_id=42"]'));
    }

    public function testEachSiteHasItsChainAndItsRows(): void
    {
        $trail = new AuditTrail($this->service(AuditRowRepository::class, AuditRowRepository::class), new JsonSerializer(), $this->service('clock', MockClock::class), siteSegments: true);
        $acme = $trail->record('orders', ['total' => '5.00'], Facts::of('order.created', 'acct-1', Subject::of('order', '7'), 'acme'));
        $trail->record('orders', ['total' => '6.00'], Facts::of('order.created', 'acct-2', Subject::of('order', '8'), 'globex'));

        $this->client->loginUser(Auditors::account('reader'));
        $crawler = $this->client->request('GET', '/admin/audit/chain');
        self::assertSame(['Shared by every site', 'Site acme', 'Site globex'], $crawler->filter('h2')->each(static fn (Crawler $title): string => trim($title->text())));
        self::assertSame(['members', 'orders', 'orders', 'orders'], $crawler->filter('tbody tr td:first-child')->each(static fn (Crawler $cell): string => trim($cell->text())));
        self::assertSame($acme->segment, $crawler->filter('tbody tr td:first-child span')->first()->attr('title'));

        $this->client->request('GET', '/admin/audit/chain/'.$acme->segment);
        self::assertSelectorTextContains('.alert-success', 'The segment holds');

        $crawler = $this->client->request('GET', '/admin/audit', ['tenant' => 'acme']);
        self::assertSame(['order.created'], $crawler->filter('tbody tr td:nth-child(3)')->each(static fn (Crawler $cell): string => trim($cell->text())));
        self::assertSame(['acme'], $crawler->filter('tbody tr td:nth-child(6)')->each(static fn (Crawler $cell): string => trim($cell->text())));
        self::assertSame('acme', $crawler->filter('#phil-audit-tenant')->attr('value'));

        $this->client->request('GET', '/admin/audit', ['tenant' => ' ']);
        self::assertResponseIsSuccessful();
        $this->client->request('GET', '/admin/audit', ['tenant' => "ac\u{0}me"]);
        self::assertResponseStatusCodeSame(400);
        self::assertSelectorTextContains('.alert-danger', 'Tenant');
    }

    public function testTheMenuLinksToTheLogAndTheChain(): void
    {
        $this->client->loginUser(Auditors::account('reader'));
        $crawler = $this->client->request('GET', '/admin/audit');

        self::assertSame(['http://localhost/admin/audit', 'http://localhost/admin/audit/chain'], $crawler->filter('#main-menu a')->each(static fn (Crawler $link): string => (string) $link->attr('href')));
        self::assertSame(['Audit log', 'Hash chain'], $crawler->filter('#main-menu a')->each(static fn (Crawler $link): string => trim($link->text())));
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

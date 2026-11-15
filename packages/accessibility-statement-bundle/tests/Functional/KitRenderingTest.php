<?php

declare(strict_types=1);

namespace Phil\AccessibilityStatementBundle\Tests\Functional;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use FilesystemIterator;
use Override;
use Phil\AccessibilityStatementBundle\Infrastructure\Doctrine\StatementTables;
use Phil\AccessibilityStatementBundle\Infrastructure\Http\StatementAdminController;
use Phil\AccessibilityStatementBundle\Infrastructure\Http\StatementController;
use Phil\AccessibilityStatementBundle\Infrastructure\Twig\KitComponents;
use Phil\AccessibilityStatementBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * The public page and the administration page drawn by each kit an application may install: the PHIL UX kit, in a
 * subdirectory of the components or at their root, and the Shadcn kit of the Symfony UX Toolkit. Each page keeps the
 * same structure whichever kit draws it — headings, list items, header cells, the label of the field — and no prop of
 * one kit leaks into the markup of the other as an attribute. No template writes a word of its own.
 */
#[CoversClass(StatementController::class)]
#[CoversClass(StatementAdminController::class)]
#[CoversClass(KitComponents::class)]
final class KitRenderingTest extends WebTestCase
{
    /** Props of one kit that are not attributes of HTML, and would be written as such by the other. */
    private const array LEAKED_PROPS = ['as', 'level', 'invalid', 'size', 'variant', 'orientation'];

    private static string $kit = 'ux-kit';

    private static string $subdirectory = 'ui';

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function kits(): iterable
    {
        yield 'PHIL UX in a subdirectory' => ['ux-kit', 'ui'];
        yield 'PHIL UX' => ['ux-kit', ''];
        yield 'Shadcn' => [TestKernel::SHADCN, ''];
    }

    /**
     * @param array<array-key, mixed> $options
     */
    #[Override]
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestKernel('test', true, self::$kit, self::$subdirectory);
    }

    #[DataProvider('kits')]
    public function testThePublicPageKeepsItsStructureWithEachKit(string $kit, string $subdirectory): void
    {
        $client = $this->client($kit, $subdirectory);
        $page = $client->request('GET', '/accessibility');

        self::assertSame(200, $client->getResponse()->getStatusCode());
        self::assertSame('Accessibility statement', $page->filter('h1')->text());
        self::assertSame(['Version', 'Published on'], $page->filter('[data-slot="table-container"] > table th[scope="col"]')->each(static fn (Crawler $cell): string => $cell->text()));
        self::assertSame('Version 2, 2026-11-02', $page->filter('[data-slot="table-cell"] a[aria-current="page"]')->text());
        self::assertSame('in force', trim($page->filter('[data-slot="table-cell"] [data-slot="badge"]')->text()));
        self::assertSame('Write to us', $page->filter('a[data-slot="button"][href="/contact"]')->text());
        self::assertSame('1.4.3 Contrast (Minimum) (level AA)', trim($page->filter('ul > li > [data-slot="card"] [data-slot="card-header"] > h4')->text()));
        self::assertCount(1, $page->filter('[data-slot="card"] [data-slot="card-content"]'));
        $this->assertNoPropLeaks($page, $client);
    }

    #[DataProvider('kits')]
    public function testTheAdministrationPageKeepsItsStructureWithEachKit(string $kit, string $subdirectory): void
    {
        $client = $this->client($kit, $subdirectory);
        $page = $client->request('GET', '/admin/accessibility-statement', server: ['PHP_AUTH_USER' => 'admin', 'PHP_AUTH_PW' => 'admin']);

        self::assertSame(200, $client->getResponse()->getStatusCode());
        self::assertSame('The statement, in JSON', $page->filter('[data-slot="field"] label[data-slot="field-label"][for="phil-accessibility-admin-statement"]')->text());
        self::assertSame('statement', $page->filter('textarea#phil-accessibility-admin-statement[data-slot="textarea"]')->attr('name'));
        self::assertSame('phil-accessibility-admin-help', $page->filter('textarea')->attr('aria-describedby'));
        self::assertSame('submit', $page->filter('button[data-slot="button"]')->attr('type'));
        self::assertCount(0, $page->filter('[data-slot="alert"]'));
        $this->assertNoPropLeaks($page, $client);

        $token = (string) $page->filter('input[name="_token"]')->attr('value');
        $refused = $client->request('POST', '/admin/accessibility-statement/publish', ['statement' => '{', '_token' => $token], server: ['PHP_AUTH_USER' => 'admin', 'PHP_AUTH_PW' => 'admin']);

        self::assertSame(422, $client->getResponse()->getStatusCode());
        self::assertSame('alert', $refused->filter('[data-slot="alert"]')->attr('role'));
        self::assertStringStartsWith('The version is not published', trim($refused->filter('[data-slot="alert"]')->text()));
        self::assertSame('true', $refused->filter('[data-slot="field"]')->attr('data-invalid'));
        self::assertStringContainsString('isDanger', (string) $refused->filter('[data-slot="field"]')->attr('class'));
        self::assertSame('true', $refused->filter('textarea')->attr('aria-invalid'));
        $this->assertNoPropLeaks($refused, $client);

        $client->request('POST', '/admin/accessibility-statement/publish', ['statement' => (string) json_encode(['version' => 3, 'published_at' => '2026-11-03']), '_token' => $token], server: ['PHP_AUTH_USER' => 'admin', 'PHP_AUTH_PW' => 'admin']);
        $published = $client->request('GET', '/admin/accessibility-statement?published=3', server: ['PHP_AUTH_USER' => 'admin', 'PHP_AUTH_PW' => 'admin']);

        self::assertSame(['alert'], $published->filter('[data-slot="alert"]')->each(static fn (Crawler $alert): ?string => $alert->attr('role')));
        self::assertSame('Version 3 is published.', trim($published->filter('[data-slot="alert"]')->text()));
    }

    /**
     * Words live in the translation catalogues, never in the markup of a template: the application translates them.
     */
    public function testNoTemplateWritesAWordOfItsOwn(): void
    {
        $templates = 0;

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(\dirname(__DIR__, 2).'/templates', FilesystemIterator::SKIP_DOTS)) as $file) {
            $this->assertInstanceOf(SplFileInfo::class, $file);
            $source = (string) preg_replace(['/\{#.*?#\}/s', '/\{%.*?%\}/s', '/\{\{.*?\}\}/s', '/"[^"]*"/s', '/<[^>]*>/s', '/&#?\w+;/'], '', (string) file_get_contents($file->getPathname()));

            self::assertDoesNotMatchRegularExpression('/\p{L}/u', $source, $file->getPathname());
            ++$templates;
        }

        self::assertGreaterThanOrEqual(7, $templates);
    }

    private function client(string $kit, string $subdirectory): KernelBrowser
    {
        if (TestKernel::SHADCN === $kit && null === TestKernel::shadcnKit()) {
            self::markTestSkipped('The Symfony UX Toolkit is not installed.');
        }

        self::$kit = $kit;
        self::$subdirectory = $subdirectory;
        $client = self::createClient();
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        self::assertInstanceOf(Connection::class, $connection);
        $schemaManager = $connection->createSchemaManager();

        if ($schemaManager->tablesExist([StatementTables::STATEMENTS])) {
            $schemaManager->dropTable(StatementTables::STATEMENTS);
        }

        foreach (StatementTables::configure(Schema::editor()->create())->toSql($connection->getDatabasePlatform()) as $sql) {
            $connection->executeStatement($sql);
        }

        $connection->insert(StatementTables::STATEMENTS, [
            'version' => 2,
            'published_at' => '2026-11-02 00:00:00',
            'document' => (string) json_encode([
                'version' => 2,
                'published_at' => '2026-11-02',
                'status' => 'partial',
                'feedback' => ['email' => 'accessibility@example.com'],
                'non_conformities' => [['criterion' => '1.4.3', 'description' => ['en' => 'Grey text.']]],
            ]),
        ]);

        return $client;
    }

    private function assertNoPropLeaks(Crawler $page, KernelBrowser $client): void
    {
        $html = (string) $client->getResponse()->getContent();
        self::assertDoesNotMatchRegularExpression('/<[^>]*\\s(role|class|type|for)="[^"]*"[^>]*\\s\\1="/', $html, 'No element carries an attribute twice.');

        foreach (self::LEAKED_PROPS as $prop) {
            self::assertCount(0, $page->filter('['.$prop.']'), \sprintf('No element carries the prop "%s" as an attribute.', $prop));
        }

        self::assertCount(0, $page->filter('[role="status"] [role="alert"], [role="alert"] [role="status"]'));
    }
}

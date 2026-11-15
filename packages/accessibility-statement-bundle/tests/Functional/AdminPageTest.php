<?php

declare(strict_types=1);

namespace Phil\AccessibilityStatementBundle\Tests\Functional;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Override;
use Phil\AccessibilityStatement\StatementReader;
use Phil\AccessibilityStatementBundle\Application\StatementPublisher;
use Phil\AccessibilityStatementBundle\Infrastructure\Doctrine\StatementTables;
use Phil\AccessibilityStatementBundle\Infrastructure\Http\StatementAdminController;
use Phil\AccessibilityStatementBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * The administration page in an application whose administrator and reader sign in by HTTP Basic: the versions, the
 * draft of the next one, and its publication behind the role and the CSRF token.
 */
#[CoversClass(StatementAdminController::class)]
#[CoversClass(StatementPublisher::class)]
final class AdminPageTest extends WebTestCase
{
    private KernelBrowser $client;

    /**
     * @param array<array-key, mixed> $options
     */
    #[Override]
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestKernel('test', true);
    }

    #[Override]
    protected function setUp(): void
    {
        $this->client = self::createClient();
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        self::assertInstanceOf(Connection::class, $connection);
        $schemaManager = $connection->createSchemaManager();

        if ($schemaManager->tablesExist([StatementTables::STATEMENTS])) {
            $schemaManager->dropTable(StatementTables::STATEMENTS);
        }

        foreach (StatementTables::configure(Schema::editor()->create())->toSql($connection->getDatabasePlatform()) as $sql) {
            $connection->executeStatement($sql);
        }
    }

    public function testThePageIsForThoseGrantedTheRole(): void
    {
        $this->client->request('GET', '/admin/accessibility-statement');
        self::assertSame(401, $this->client->getResponse()->getStatusCode());

        $this->client->request('GET', '/admin/accessibility-statement', server: ['PHP_AUTH_USER' => 'reader', 'PHP_AUTH_PW' => 'reader']);
        self::assertSame(403, $this->client->getResponse()->getStatusCode());

        $this->client->request('POST', '/admin/accessibility-statement/publish', ['statement' => '{}'], server: ['PHP_AUTH_USER' => 'reader', 'PHP_AUTH_PW' => 'reader']);
        self::assertSame(403, $this->client->getResponse()->getStatusCode());
    }

    public function testTheAdministratorPublishesTheNextVersionFromItsDraft(): void
    {
        $crawler = $this->client->request('GET', '/admin/accessibility-statement', server: $this->admin());
        $response = $this->client->getResponse();
        $html = (string) $response->getContent();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        self::assertStringContainsString('<title>Accessibility statement: administration</title>', $html);
        self::assertStringContainsString('<table data-slot="table" class="isTable">', $html);
        self::assertStringContainsString('<a href="/accessibility/v1" aria-current="page">Version 1, 2026-11-01</a>', $html);
        self::assertStringContainsString('<div role="group" data-slot="field" data-orientation="vertical" class="isField">', $html);
        self::assertStringContainsString('<label data-slot="field-label" class="isFieldLabel" for="phil-accessibility-admin-statement">The statement, in JSON</label>', $html);
        self::assertStringNotContainsString('data-slot="alert"', $html);
        $draft = $crawler->filter('textarea[name="statement"]')->text();
        self::assertSame(2, StatementReader::fromJson($draft)->version);
        $token = (string) $crawler->filter('input[name="_token"]')->attr('value');
        self::assertNotSame('', $token);

        $this->client->request('POST', '/admin/accessibility-statement/publish', ['statement' => $draft, '_token' => 'forged'], server: $this->admin());
        self::assertSame(403, $this->client->getResponse()->getStatusCode(), 'a forged token publishes nothing');

        $this->client->request('POST', '/admin/accessibility-statement/publish', ['statement' => '{"version": 2', '_token' => $token], server: $this->admin());
        $refused = $this->client->getResponse();
        self::assertSame(422, $refused->getStatusCode());
        self::assertStringContainsString('data-slot="alert" data-variant="destructive"', (string) $refused->getContent());
        self::assertStringContainsString('The document does not read as a statement: The statement is not JSON', (string) $refused->getContent());
        self::assertStringContainsString('{&quot;version&quot;: 2</textarea>', (string) $refused->getContent(), 'the document comes back to be corrected');
        self::assertStringContainsString('aria-invalid="true"', (string) $refused->getContent());

        $this->client->request('POST', '/admin/accessibility-statement/publish', ['statement' => $draft, '_token' => $token], server: $this->admin());
        $published = $this->client->getResponse();
        self::assertSame(303, $published->getStatusCode());
        self::assertSame('/admin/accessibility-statement?published=2', $published->headers->get('Location'));

        $this->client->request('GET', '/admin/accessibility-statement?published=2', server: $this->admin());
        $page = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('data-slot="alert" data-variant="default" class="isAlert" role="alert"', $page);
        self::assertStringContainsString('Version 2 is published.', $page);
        self::assertStringContainsString('<a href="/accessibility/v2" aria-current="page">Version 2,', $page);

        $this->client->request('GET', '/accessibility');
        self::assertStringContainsString('This statement, version 2, was published on', (string) $this->client->getResponse()->getContent());
    }

    /**
     * @return array<string, string>
     */
    private function admin(): array
    {
        return ['PHP_AUTH_USER' => 'admin', 'PHP_AUTH_PW' => 'admin'];
    }
}

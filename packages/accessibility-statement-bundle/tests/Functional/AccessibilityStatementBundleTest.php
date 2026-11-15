<?php

declare(strict_types=1);

namespace Phil\AccessibilityStatementBundle\Tests\Functional;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Override;
use Phil\AccessibilityCheck\AccessibilityChecker;
use Phil\AccessibilityStatementBundle\Infrastructure\Doctrine\StatementTables;
use Phil\AccessibilityStatementBundle\PhilAccessibilityStatementBundle;
use Phil\AccessibilityStatementBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Service\ResetInterface;
use Twig\Environment;

/**
 * The bundle in an application, its table rebuilt for each test; each request goes through the kernel as a worker
 * would serve it, terminated, then the services reset.
 */
#[CoversClass(PhilAccessibilityStatementBundle::class)]
final class AccessibilityStatementBundleTest extends KernelTestCase
{
    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    #[Override]
    protected function setUp(): void
    {
        self::bootKernel();
        $connection = $this->connection();
        $schemaManager = $connection->createSchemaManager();

        if ($schemaManager->tablesExist([StatementTables::STATEMENTS])) {
            $schemaManager->dropTable(StatementTables::STATEMENTS);
        }

        foreach (StatementTables::configure(Schema::editor()->create())->toSql($connection->getDatabasePlatform()) as $sql) {
            $connection->executeStatement($sql);
        }

        $directory = $this->kernel()->auditDirectory();

        $files = glob($directory.'/*.json');

        foreach (false === $files ? [] : $files as $file) {
            unlink($file);
        }

        if (!is_dir($directory)) {
            mkdir($directory, 0o777, true);
        }
    }

    public function testTheStatementIsServedInEachLanguage(): void
    {
        $english = $this->request('/accessibility');
        self::assertSame(200, $english->getStatusCode());
        self::assertSame('max-age=3600, public', $english->headers->get('Cache-Control'));
        self::assertStringContainsString('<html lang="en">', (string) $english->getContent());
        self::assertStringContainsString('Example Org is committed to making this site accessible', (string) $english->getContent());
        self::assertStringContainsString('class="isButton isOutline" href="/contact">Write to us</a>', (string) $english->getContent());

        $french = $this->request('/accessibilite');
        self::assertSame(200, $french->getStatusCode());
        self::assertStringContainsString('<html lang="fr">', (string) $french->getContent());
        self::assertStringContainsString('Example Org s’engage à rendre ce site accessible', (string) $french->getContent());

        self::assertSame(200, $this->request('/accessibility/v1')->getStatusCode());
        self::assertSame(404, $this->request('/accessibility/v2')->getStatusCode());
        self::assertSame(404, $this->request('/accessibility/v0')->getStatusCode());
    }

    public function testTheFooterLinksToThePageOfItsLanguage(): void
    {
        $twig = self::getContainer()->get('twig');
        self::assertInstanceOf(Environment::class, $twig);

        self::assertSame("<footer><a href=\"/accessibility\">Accessibility</a></footer>\n", $twig->render('footer.html.twig'));
    }

    public function testTheCommandPublishesTheNextVersionFromTheAudit(): void
    {
        file_put_contents($this->kernel()->auditDirectory().'/home.json', (string) json_encode(new AccessibilityChecker()->checkHtml('<img src="/a.jpg">')));

        $application = new Application($this->kernel());
        $tester = new CommandTester($application->find('phil:accessibility-statement:generate'));

        self::assertSame(0, $tester->execute([]));
        self::assertStringContainsString('Version 2 of the accessibility statement is published.', $tester->getDisplay());

        $page = (string) $this->request('/accessibility')->getContent();
        self::assertStringContainsString('1.1.1 Non-text Content (level A)', $page);
        self::assertStringContainsString('partially conforms', $page);
        self::assertStringContainsString('<a href="/accessibility/v1">Version 1, 2026-11-01</a>', $page);
        self::assertSame(200, $this->request('/accessibility/v1')->getStatusCode());
    }

    private function request(string $path): Response
    {
        $kernel = $this->kernel();
        $request = Request::create($path);
        $response = $kernel->handle($request);

        $kernel->terminate($request, $response);

        $resetter = self::getContainer()->get('services_resetter');
        self::assertInstanceOf(ResetInterface::class, $resetter);
        $resetter->reset();

        return $response;
    }

    private function connection(): Connection
    {
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        self::assertInstanceOf(Connection::class, $connection);

        return $connection;
    }

    private function kernel(): TestKernel
    {
        $kernel = self::$kernel;
        self::assertInstanceOf(TestKernel::class, $kernel);

        return $kernel;
    }
}

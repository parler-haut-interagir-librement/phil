<?php

declare(strict_types=1);

namespace Phil\TermsBundle\Tests\Functional;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Override;
use Phil\TermsBundle\Application\Terms;
use Phil\TermsBundle\Infrastructure\Doctrine\TermsTables;
use Phil\TermsBundle\Infrastructure\Http\Pages;
use Phil\TermsBundle\PhilTermsBundle;
use Phil\TermsBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\TerminableInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The bundle in an application, with its two tables rebuilt for each test; each request goes through the kernel as a
 * worker would serve it, terminated, then the services reset.
 */
#[CoversClass(PhilTermsBundle::class)]
final class TermsBundleTest extends KernelTestCase
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

        foreach ([TermsTables::VERSIONS, TermsTables::ACCEPTANCES] as $table) {
            if ($schemaManager->tablesExist([$table])) {
                $schemaManager->dropTable($table);
            }
        }

        foreach (TermsTables::configure(Schema::editor()->create())->toSql($connection->getDatabasePlatform()) as $sql) {
            $connection->executeStatement($sql);
        }
    }

    public function testAMemberAcceptsANewVersionBeforeGoingOn(): void
    {
        $this->terms()->publish('terms', ['en' => 'The terms.', 'fr' => 'Les conditions.']);
        $this->terms()->publish('privacy', ['en' => 'The privacy policy.']);

        self::assertSame('forum', $this->request('GET', '/forum')->getContent());

        $stopped = $this->request('GET', '/forum', ['X-Member' => '42']);
        self::assertSame(303, $stopped->getStatusCode());
        self::assertSame('/terms/accept?target=/forum', $stopped->headers->get('Location'));

        $page = $this->request('GET', '/terms/accept?target=/forum', ['X-Member' => '42', 'Accept-Language' => 'fr']);
        self::assertSame(200, $page->getStatusCode());
        self::assertStringContainsString('<p>The terms.</p>', (string) $page->getContent());

        $accepted = $this->post('/terms/accept?target=/forum', ['accept' => ['terms' => '1', 'privacy' => '1']]);
        self::assertSame(303, $accepted->getStatusCode());
        self::assertSame('/forum', $accepted->headers->get('Location'));
        self::assertSame('forum', $this->request('GET', '/forum', ['X-Member' => '42'])->getContent());
        self::assertEquals(2, $this->connection()->fetchOne('SELECT COUNT(*) FROM phil_terms_acceptance WHERE member = ?', ['42']));

        $this->terms()->publish('terms', ['en' => 'The new terms.']);
        self::assertSame(303, $this->request('GET', '/forum', ['X-Member' => '42'])->getStatusCode());
    }

    public function testAnApiIsAnsweredWithAProblem(): void
    {
        $this->terms()->publish('terms', ['en' => 'The terms.']);

        $response = $this->request('GET', '/api/posts', ['X-Member' => '42']);

        self::assertSame(403, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->headers->get('Content-Type'));
        self::assertStringContainsString('"url":"\/terms\/terms\/v1"', (string) $response->getContent());
    }

    public function testThePublicPagesAndTheChanges(): void
    {
        $this->terms()->publish('terms', ['en' => 'One rule.']);
        $this->terms()->publish('terms', ['en' => 'One new rule.'], material: false);

        self::assertStringContainsString('<p>One new rule.</p>', (string) $this->request('GET', '/terms/terms')->getContent());
        self::assertStringContainsString('<p>One rule.</p>', (string) $this->request('GET', '/terms/terms/v1')->getContent());
        self::assertStringContainsString('<ins>new </ins>', (string) $this->request('GET', '/terms/terms/v2/changes/1')->getContent());
        self::assertSame(404, $this->request('GET', '/terms/cookies')->getStatusCode());
    }

    public function testTheAdministrationPublishesAVersion(): void
    {
        self::assertSame(403, $this->request('GET', '/admin/terms/', ['X-Roles' => 'ROLE_USER'])->getStatusCode());
        self::assertStringContainsString('No version yet.', (string) $this->request('GET', '/admin/terms/', ['X-Roles' => 'ROLE_LEGAL'])->getContent());

        $published = $this->post('/admin/terms/terms/publish', ['texts' => ['en' => 'Published terms.', 'fr' => 'Conditions publiées.']], ['X-Roles' => 'ROLE_LEGAL']);
        self::assertSame(303, $published->getStatusCode());
        self::assertSame('/admin/terms/?done=published', $published->headers->get('Location'));
        self::assertStringContainsString('<p>Published terms.</p>', (string) $this->request('GET', '/terms/terms')->getContent());
    }

    private function terms(): Terms
    {
        $terms = self::getContainer()->get(Terms::class);
        self::assertInstanceOf(Terms::class, $terms);

        return $terms;
    }

    private function connection(): Connection
    {
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        self::assertInstanceOf(Connection::class, $connection);

        return $connection;
    }

    /**
     * @param array<string, string> $headers
     * @param array<string, mixed>  $form
     */
    private function request(string $method, string $uri, array $headers = [], array $form = []): Response
    {
        $kernel = self::$kernel;
        self::assertInstanceOf(HttpKernelInterface::class, $kernel);
        $request = Request::create($uri, $method, $form);
        $request->headers->add($headers);

        $response = $kernel->handle($request);

        if ($kernel instanceof TerminableInterface) {
            $kernel->terminate($request, $response);
        }

        $resetter = self::getContainer()->get('services_resetter');
        self::assertInstanceOf(ResetInterface::class, $resetter);
        $resetter->reset();

        return $response;
    }

    /**
     * @param array<string, mixed>  $form
     * @param array<string, string> $headers
     */
    private function post(string $uri, array $form, array $headers = ['X-Member' => '42']): Response
    {
        $csrf = self::getContainer()->get('security.csrf.token_manager');
        self::assertInstanceOf(CsrfTokenManagerInterface::class, $csrf);

        return $this->request('POST', $uri, $headers, $form + ['_token' => $csrf->getToken(Pages::CSRF_TOKEN_ID)->getValue()]);
    }
}

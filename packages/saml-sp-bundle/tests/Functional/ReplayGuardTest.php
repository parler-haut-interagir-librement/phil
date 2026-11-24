<?php

declare(strict_types=1);

namespace Phil\SamlSpBundle\Tests\Functional;

use DateTimeImmutable;
use Doctrine\DBAL\DriverManager;
use Override;
use Phil\SamlSp\Store\DbalReplayGuard;
use Phil\SamlSpBundle\Infrastructure\DependencyInjection\ReplayGuardPass;
use Phil\SamlSpBundle\Tests\Fixtures\Saml;
use Phil\SamlSpBundle\Tests\Functional\App\Refusals;
use Phil\SamlSpBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * The store of the accepted assertions in the application: the table of the Doctrine connection when there is one, the
 * cache pool otherwise, and the cache pool on the file system refused in production.
 */
#[CoversClass(ReplayGuardPass::class)]
final class ReplayGuardTest extends WebTestCase
{
    /**
     * The directory of this test's kernels and SQLite file, absolute and its own: neither the current directory nor
     * another test that shares the temporary directory reaches it.
     */
    private static string $directory = '';

    #[Override]
    protected function setUp(): void
    {
        $temporary = realpath(sys_get_temp_dir());
        self::assertIsString($temporary);
        self::$directory = $temporary.'/phil-saml-sp-bundle-replay-'.bin2hex(random_bytes(8));
        self::assertTrue(mkdir(self::$directory, 0o700, true));
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        self::remove(self::$directory);
    }

    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    /**
     * @param array<array-key, mixed> $options
     */
    #[Override]
    protected static function createKernel(array $options = []): TestKernel
    {
        $environment = $options['environment'] ?? 'test';
        $debug = $options['debug'] ?? true;
        self::assertIsString($environment);
        self::assertIsBool($debug);

        return new TestKernel($environment, $debug, self::$directory);
    }

    public function testWithDoctrineAReplayedAssertionIsRefusedByTheTable(): void
    {
        $kernel = new TestKernel(TestKernel::DOCTRINE, true, self::$directory);
        self::assertStringStartsWith(self::$directory.'/', $kernel->database());
        self::assertFileDoesNotExist($kernel->database());

        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => $kernel->database()]);
        $guard = new DbalReplayGuard($connection, new NativeClock());
        $manager = $connection->createSchemaManager();
        $manager->migrateSchema($guard->configureSchema($manager->introspectSchema()));

        $client = $this->browser(TestKernel::DOCTRINE);
        $response = Saml::response(null, new DateTimeImmutable(), base: 'https://app.example.com/saml/portal');

        $client->request('POST', '/saml/portal/acs', ['SAMLResponse' => $response, 'RelayState' => '/dashboard']);
        self::assertResponseRedirects('/dashboard');
        self::assertEquals(1, $connection->fetchOne('SELECT COUNT(*) FROM '.DbalReplayGuard::TABLE));

        $client->request('POST', '/saml/portal/acs', ['SAMLResponse' => $response, 'RelayState' => '/dashboard']);
        self::assertResponseStatusCodeSame(403);
        self::assertSame(['portal: The assertion was already accepted once.'], $this->refusals($client)->reasons);
        self::assertEquals(1, $connection->fetchOne('SELECT COUNT(*) FROM '.DbalReplayGuard::TABLE));
        $connection->close();
    }

    public function testWithoutDoctrineTheCachePoolRefusesAReplayedAssertion(): void
    {
        $client = $this->browser('test');
        $response = Saml::response(null, new DateTimeImmutable(), base: 'https://app.example.com/saml/portal');

        $client->request('POST', '/saml/portal/acs', ['SAMLResponse' => $response, 'RelayState' => '/dashboard']);
        self::assertResponseRedirects('/dashboard');

        $client->request('POST', '/saml/portal/acs', ['SAMLResponse' => $response, 'RelayState' => '/dashboard']);
        self::assertResponseStatusCodeSame(403);
        self::assertSame(['portal: The assertion was already accepted once.'], $this->refusals($client)->reasons);
    }

    public function testAPoolOnTheFileSystemIsRefusedInProduction(): void
    {
        $kernel = new TestKernel('prod', false, self::$directory);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('phil_saml_sp.cache_pool: the pool "cache.app" keeps the accepted assertions on each server');

        $kernel->boot();
    }

    private static function remove(string $path): void
    {
        if (is_dir($path) && !is_link($path)) {
            $entries = scandir($path);
            self::assertIsArray($entries);

            foreach (array_diff($entries, ['.', '..']) as $entry) {
                self::remove($path.'/'.$entry);
            }

            rmdir($path);
        } elseif (is_file($path) || is_link($path)) {
            unlink($path);
        }
    }

    private function refusals(KernelBrowser $client): Refusals
    {
        $refusals = $client->getContainer()->get(Refusals::class);
        self::assertInstanceOf(Refusals::class, $refusals);

        return $refusals;
    }

    private function browser(string $environment): KernelBrowser
    {
        $client = self::createClient(['environment' => $environment]);
        $client->disableReboot();
        $client->setServerParameter('HTTPS', 'on');
        $client->setServerParameter('HTTP_HOST', 'app.example.com');

        return $client;
    }
}

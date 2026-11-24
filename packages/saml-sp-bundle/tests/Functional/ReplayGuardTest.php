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
    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    public function testWithDoctrineAReplayedAssertionIsRefusedByTheTable(): void
    {
        $kernel = new TestKernel(TestKernel::DOCTRINE, true);
        if (is_file($kernel->database())) {
            unlink($kernel->database());
        } elseif (!is_dir(\dirname($kernel->database()))) {
            mkdir(\dirname($kernel->database()), 0o777, true);
        }

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
        $kernel = new TestKernel('prod', false);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('phil_saml_sp.cache_pool: the pool "cache.app" keeps the accepted assertions on each server');

        $kernel->boot();
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

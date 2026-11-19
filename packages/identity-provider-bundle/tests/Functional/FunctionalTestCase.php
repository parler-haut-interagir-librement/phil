<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\IdentityProviderBundle\Application\Accounts;
use Phil\IdentityProviderBundle\Domain\Contract\AccountRepository;
use Phil\IdentityProviderBundle\Domain\Model\Account\Account;
use Phil\IdentityProviderBundle\Tests\Functional\App\TestKernel;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * A browser on the test application, its tables created afresh, with the helpers every flow starts from.
 */
abstract class FunctionalTestCase extends WebTestCase
{
    protected const string PASSWORD = 'correct horse battery staple violet';

    protected KernelBrowser $client;

    /**
     * @param array<array-key, mixed> $options
     */
    #[Override]
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestKernel();
    }

    #[Override]
    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->client->disableReboot();

        $entityManager = $this->service(EntityManagerInterface::class, 'doctrine.orm.entity_manager');
        $tool = new SchemaTool($entityManager);
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
        // The counts of the limiters start afresh with each test.
        $this->service(AdapterInterface::class, 'cache.app')->clear();
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    protected function service(string $class, ?string $id = null): object
    {
        $service = self::getContainer()->get($id ?? $class);
        self::assertInstanceOf($class, $service);

        return $service;
    }

    protected function account(string $email = 'ada@example.com', bool $verified = true): Account
    {
        $accounts = $this->service(Accounts::class);
        $account = $accounts->register($email, self::PASSWORD, 'Ada');
        self::assertNotNull($account);

        if ($verified) {
            $accounts->verifyEmail($account);
        }

        return $account;
    }

    /**
     * @phpstan-impure
     */
    protected function accounts(): AccountRepository
    {
        return $this->service(AccountRepository::class);
    }

    protected function signIn(string $email = 'ada@example.com', string $password = self::PASSWORD): void
    {
        $this->client->request('POST', '/login', ['_username' => $email, '_password' => $password]);
    }

    /**
     * @phpstan-impure
     */
    protected function content(): string
    {
        return (string) $this->client->getResponse()->getContent();
    }

    protected function location(): string
    {
        return (string) $this->client->getResponse()->headers->get('Location');
    }
}

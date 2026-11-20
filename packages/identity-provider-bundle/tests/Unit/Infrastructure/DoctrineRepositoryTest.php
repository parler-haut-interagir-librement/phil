<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Override;
use Phil\IdentityProviderBundle\Domain\Model\Account\Account;
use Phil\IdentityProviderBundle\Domain\Model\Session\AccountSession;
use Phil\IdentityProviderBundle\Domain\Model\Totp\TotpCredential;
use Phil\IdentityProviderBundle\Infrastructure\Doctrine\DoctrineAccountRepository;
use Phil\IdentityProviderBundle\Infrastructure\Doctrine\DoctrineAccountSessionRepository;
use Phil\IdentityProviderBundle\Infrastructure\Doctrine\DoctrineClientRepository;
use Phil\IdentityProviderBundle\Infrastructure\Doctrine\DoctrineGrantRepository;
use Phil\IdentityProviderBundle\Infrastructure\Doctrine\DoctrineLinkedIdentityRepository;
use Phil\IdentityProviderBundle\Infrastructure\Doctrine\DoctrinePasskeyRepository;
use Phil\IdentityProviderBundle\Infrastructure\Doctrine\DoctrineSecondFactorRepository;
use Phil\IdentityProviderBundle\Infrastructure\Doctrine\DoctrineSigningKeyRepository;
use Phil\IdentityProviderBundle\Infrastructure\Doctrine\DoctrineTransactions;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;

/**
 * The Doctrine stores on SQLite in memory: the eleven tables of the six modules.
 */
#[CoversClass(DoctrineAccountRepository::class)]
#[CoversClass(DoctrinePasskeyRepository::class)]
#[CoversClass(DoctrineSecondFactorRepository::class)]
#[CoversClass(DoctrineAccountSessionRepository::class)]
#[CoversClass(DoctrineLinkedIdentityRepository::class)]
#[CoversClass(DoctrineClientRepository::class)]
#[CoversClass(DoctrineGrantRepository::class)]
#[CoversClass(DoctrineSigningKeyRepository::class)]
#[CoversClass(DoctrineTransactions::class)]
final class DoctrineRepositoryTest extends DoctrineRepositoryTestCase
{
    #[Override]
    protected function connection(): Connection
    {
        return DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    }

    public function testAChangeReadsTheRowAgainOverWhatTheUnitOfWorkHolds(): void
    {
        $now = new DateTimeImmutable('2026-11-14 10:00:00');
        $this->factors()->save(TotpCredential::enroll(self::ADA, 'plain.SECRET', $now));
        $held = $this->factors()->totp(self::ADA);
        self::assertNotNull($held);
        $this->entityManager()->getConnection()->executeStatement('UPDATE phil_identity_totp SET last_counter = 99');
        $seen = null;

        self::assertFalse($this->factors()->apply($held, static function (TotpCredential $current) use (&$seen): bool {
            $seen = $current->lastCounter();

            return false;
        }));
        self::assertSame(99, $seen);
    }

    public function testATransactionUndoesTheWritesOfEveryStoreWhenItsWorkThrows(): void
    {
        $now = new DateTimeImmutable('2026-11-14 10:00:00');
        $this->accounts()->save(Account::register(self::ADA, 'ada@example.com', $now));

        try {
            $this->transactions()->run(function () use ($now): never {
                $this->sessions()->save(AccountSession::open(str_repeat('a', 43), self::ADA, 'A', [], false, $now));

                throw new RuntimeException('failed');
            });
        } catch (RuntimeException) {
        }

        $this->clear();
        self::assertNull($this->sessions()->get(str_repeat('a', 43)));
    }

    public function testTheTables(): void
    {
        $connection = $this->entityManager()->getConnection();
        $tables = $connection->createSchemaManager()->introspectTableNames();
        $names = array_map(static fn (OptionallyQualifiedName $name): string => $name->toString(), $tables);
        sort($names);

        self::assertSame(['phil_identity_account', 'phil_identity_assertion', 'phil_identity_client', 'phil_identity_code', 'phil_identity_consent', 'phil_identity_linked', 'phil_identity_passkey', 'phil_identity_recovery_codes', 'phil_identity_session', 'phil_identity_signing_key', 'phil_identity_token', 'phil_identity_totp'], array_map(static fn (string $name): string => trim($name, '"`'), $names));
    }
}

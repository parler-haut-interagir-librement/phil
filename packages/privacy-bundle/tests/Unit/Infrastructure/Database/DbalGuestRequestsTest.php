<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Infrastructure\Database;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Override;
use Phil\PrivacyBundle\Domain\GuestRequest\GuestRequest;
use Phil\PrivacyBundle\Domain\GuestRequest\GuestRequestKind;
use Phil\PrivacyBundle\Infrastructure\Database\DbalGuestRequests;
use Phil\PrivacyBundle\Infrastructure\Database\PrivacyTables;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DbalGuestRequests::class)]
final class DbalGuestRequestsTest extends TestCase
{
    private Connection $connection;

    #[Override]
    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);

        foreach (PrivacyTables::configure(Schema::editor()->create())->toSql($this->connection->getDatabasePlatform()) as $statement) {
            $this->connection->executeStatement($statement);
        }
    }

    public function testDeletingARequestLeavesTheOthers(): void
    {
        $store = new DbalGuestRequests($this->connection, 'secret');
        $kept = $this->request('a@example.org');
        $deleted = $this->request('b@example.org');
        $store->save($kept);
        $store->save($deleted);

        $store->delete($deleted->id);

        self::assertNull($store->find($deleted->id));
        self::assertInstanceOf(GuestRequest::class, $store->find($kept->id));
    }

    public function testARetainedRequestIsKeptUntilItsDateAndItsLinkOpensItNoMore(): void
    {
        $store = new DbalGuestRequests($this->connection, 'secret');
        [$request, $token] = GuestRequest::issue(GuestRequestKind::Erasure, 'a@example.org', new DateTimeImmutable('2026-11-10 12:00:00 UTC'), new DateTimeImmutable('2026-11-10 13:00:00 UTC'));
        $other = $this->request('b@example.org');
        $store->save($request);
        $store->save($other);

        $store->retain($request->id, new DateTimeImmutable('2027-02-08 12:00:00 UTC'));

        $kept = $store->find($request->id);
        self::assertSame('a@example.org', $kept?->email);
        self::assertSame('2027-02-08 12:00:00', $kept->expiresAt->format('Y-m-d H:i:s'));
        self::assertFalse($kept->isOpenedBy($token));
        self::assertNotSame($kept->tokenHash, $store->find($other->id)?->tokenHash);
        self::assertSame($other->tokenHash, $store->find($other->id)?->tokenHash, 'Only the request named is retained.');
        self::assertSame(1, $store->purgeExpired(new DateTimeImmutable('2027-02-08 11:59:59 UTC')));
        self::assertNotNull($store->find($request->id));
        self::assertSame(1, $store->purgeExpired(new DateTimeImmutable('2027-02-08 12:00:00 UTC')));
    }

    public function testTheAddressIsSealedUnderTheHkdfKeyOfItsLabel(): void
    {
        new DbalGuestRequests($this->connection, 'secret')->save($this->request('a@example.org'));
        $email = $this->connection->fetchOne('SELECT email FROM '.PrivacyTables::GUEST_REQUESTS);
        self::assertIsString($email);
        $sealed = sodium_base642bin($email, \SODIUM_BASE64_VARIANT_ORIGINAL);
        $nonce = substr($sealed, 0, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $box = substr($sealed, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        self::assertSame('a@example.org', sodium_crypto_secretbox_open($box, $nonce, hash_hkdf('sha256', 'secret', 32, 'phil_privacy.guest_request_store')));
        self::assertFalse(sodium_crypto_secretbox_open($box, $nonce, hash_hmac('sha256', 'phil_privacy.guest_request_store', 'secret', true)));
    }

    public function testASealedAddressTooShortToOpenReadsAsAbsent(): void
    {
        $store = new DbalGuestRequests($this->connection, 'secret');
        $request = $this->request('a@example.org');

        foreach ([20, 30, 40] as $length) {
            $store->save($request);
            $this->connection->update(PrivacyTables::GUEST_REQUESTS, ['email' => base64_encode(random_bytes($length))], ['id' => $request->id]);
            self::assertNull($store->find($request->id), (string) $length);
            $store->delete($request->id);
        }
    }

    public function testARowWithoutItsHashOrItsDatesReadsAsAbsent(): void
    {
        $store = new DbalGuestRequests($this->connection, 'secret');
        $request = $this->request('a@example.org');
        $store->save($request);
        $row = $this->connection->fetchAssociative('SELECT * FROM '.PrivacyTables::GUEST_REQUESTS);
        self::assertIsArray($row);

        foreach (['token_hash', 'requested_at', 'expires_at'] as $column) {
            foreach ('token_hash' === $column ? [null] : [null, 'tomorrow'] as $value) {
                $connection = self::createStub(Connection::class);
                $connection->method('fetchAssociative')->willReturn([$column => $value] + $row);
                $connection->method('getDatabasePlatform')->willReturn($this->connection->getDatabasePlatform());

                self::assertNull(new DbalGuestRequests($connection, 'secret')->find($request->id), $column.' '.var_export($value, true));
            }
        }

        $connection = self::createStub(Connection::class);
        $connection->method('fetchAssociative')->willReturn($row);
        $connection->method('getDatabasePlatform')->willReturn($this->connection->getDatabasePlatform());
        self::assertInstanceOf(GuestRequest::class, new DbalGuestRequests($connection, 'secret')->find($request->id));
    }

    private function request(string $email): GuestRequest
    {
        $now = new DateTimeImmutable('2026-11-10T12:00:00Z');

        return GuestRequest::issue(GuestRequestKind::Access, $email, $now, $now->modify('+1 hour'))[0];
    }
}

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
use Phil\PrivacyBundle\Domain\Incident\Incident;
use Phil\PrivacyBundle\Domain\Retention\PurgeOrder;
use Phil\PrivacyBundle\Domain\Subject\DataCategory;
use Phil\PrivacyBundle\Infrastructure\Database\DbalGuestRequests;
use Phil\PrivacyBundle\Infrastructure\Database\DbalIncidentRegister;
use Phil\PrivacyBundle\Infrastructure\Database\PrivacyTables;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(DbalGuestRequests::class)]
#[CoversClass(DbalIncidentRegister::class)]
final class GuestAndIncidentTablesTest extends TestCase
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

    public function testARequestIsKeptWithItsAddressSealed(): void
    {
        $store = new DbalGuestRequests($this->connection, 'secret');
        $now = new DateTimeImmutable('2026-11-10T12:00:00+02:00');
        [$request] = GuestRequest::issue(GuestRequestKind::Erasure, 'eloise@example.org', $now, $now->modify('+1 hour'));
        $store->save($request);

        $row = $this->connection->fetchAssociative('SELECT * FROM '.PrivacyTables::GUEST_REQUESTS);
        self::assertIsArray($row);
        self::assertStringNotContainsString('eloise', (string) json_encode($row));
        self::assertSame('2026-11-10 10:00:00', $row['requested_at']);
        self::assertSame('2026-11-10 11:00:00', $row['expires_at']);
        self::assertSame('erasure', $row['kind']);

        $found = $store->find($request->id);
        self::assertInstanceOf(GuestRequest::class, $found);
        self::assertSame(['eloise@example.org', GuestRequestKind::Erasure, $request->tokenHash], [$found->email, $found->kind, $found->tokenHash]);
        self::assertEquals($request->requestedAt, $found->requestedAt);
        self::assertEquals($request->expiresAt, $found->expiresAt);
        self::assertNull($store->find(str_repeat('0', 32)));

        self::assertNull(new DbalGuestRequests($this->connection, 'another secret')->find($request->id), 'Another key opens nothing.');

        $store->delete($request->id);
        self::assertNull($store->find($request->id));
    }

    public function testARowThatCannotBeOpenedReadsAsAbsent(): void
    {
        $store = new DbalGuestRequests($this->connection, 'secret');
        $now = new DateTimeImmutable('2026-11-10T12:00:00Z');
        [$request] = GuestRequest::issue(GuestRequestKind::Access, 'a@example.org', $now, $now->modify('+1 hour'));
        $store->save($request);

        foreach (['email' => 'not base64 !', 'kind' => 'other'] as $column => $value) {
            $this->connection->update(PrivacyTables::GUEST_REQUESTS, [$column => $value], ['id' => $request->id]);
            self::assertNull($store->find($request->id), $column);
            $this->connection->delete(PrivacyTables::GUEST_REQUESTS, ['id' => $request->id]);
            $store->save($request);
        }

        $this->connection->update(PrivacyTables::GUEST_REQUESTS, ['email' => base64_encode('short')], ['id' => $request->id]);
        self::assertNull($store->find($request->id));
    }

    public function testExpiredRequestsArePurged(): void
    {
        $store = new DbalGuestRequests($this->connection, 'secret');
        $now = new DateTimeImmutable('2026-11-10T12:00:00Z');
        [$early] = GuestRequest::issue(GuestRequestKind::Access, 'a@example.org', $now, $now->modify('+1 hour'));
        [$late] = GuestRequest::issue(GuestRequestKind::Access, 'b@example.org', $now, $now->modify('+2 hours'));
        $store->save($early);
        $store->save($late);

        self::assertSame(0, $store->purgeExpired($now->modify('+59 minutes')));
        self::assertSame(1, $store->purgeExpired($now->modify('+1 hour')));
        self::assertNull($store->find($early->id));
        self::assertNotNull($store->find($late->id));
    }

    public function testAnIncidentIsWrittenReadAndCompleted(): void
    {
        $register = new DbalIncidentRegister($this->connection);
        $discovered = new DateTimeImmutable('2026-11-10T09:00:00+00:00');
        $incident = Incident::record('List exported', $discovered, $discovered->modify('-2 hours'), [DataCategory::fromString('email'), DataCategory::fromString('phone')], 120, true, 'Folder removed');
        $register->save($incident);

        $found = $register->find($incident->id);
        self::assertInstanceOf(Incident::class, $found);
        self::assertSame(['List exported', 120, true, 'Folder removed', ['email', 'phone']], [$found->summary, $found->affected, $found->seriousRisk, $found->measures, array_map(static fn (DataCategory $category): string => $category->value, $found->categories)]);
        self::assertEquals($discovered, $found->discoveredAt);
        self::assertEquals($discovered->modify('-2 hours'), $found->occurredAt);
        self::assertNull($found->closedAt);

        $register->save($found->update(authorityNotifiedAt: $discovered->modify('+1 day'), subjectsNotifiedAt: $discovered->modify('+2 days'), closedAt: $discovered->modify('+3 days')));
        $closed = $register->find($incident->id);
        self::assertInstanceOf(Incident::class, $closed);
        self::assertEquals($discovered->modify('+1 day'), $closed->authorityNotifiedAt);
        self::assertEquals($discovered->modify('+2 days'), $closed->subjectsNotifiedAt);
        self::assertTrue($closed->isClosed());
        self::assertEquals(1, $this->connection->fetchOne('SELECT COUNT(*) FROM '.PrivacyTables::INCIDENTS));
        self::assertNull($register->find(str_repeat('0', 32)));
    }

    public function testTheRegisterListsTheMostRecentFirstAndPurgesOnlyWhatIsClosed(): void
    {
        $register = new DbalIncidentRegister($this->connection, new MockClock('2026-11-20T10:00:00Z'));
        $old = Incident::record('Old', new DateTimeImmutable('2020-01-01T00:00:00Z'), null, [], null, false);
        $open = Incident::record('Old and open', new DateTimeImmutable('2020-06-01T00:00:00Z'), null, [], null, false);
        $recent = Incident::record('Recent', new DateTimeImmutable('2026-01-01T00:00:00Z'), null, [], null, false);
        $register->save($old->update(closedAt: new DateTimeImmutable('2020-02-01T00:00:00Z')));
        $register->save($open);
        $register->save($recent->update(closedAt: new DateTimeImmutable('2026-02-01T00:00:00Z')));

        self::assertSame(['Recent', 'Old and open', 'Old'], array_map(static fn (Incident $incident): string => $incident->summary, $register->all()));
        self::assertNull($register->all()[1]->affected);
        self::assertSame(PrivacyTables::INCIDENTS, $register->store());
        self::assertSame(1, $register->purge(new PurgeOrder('R-1', PrivacyTables::INCIDENTS, new DateTimeImmutable('2021-11-10T00:00:00Z'))));
        self::assertSame(['Recent', 'Old and open'], array_map(static fn (Incident $incident): string => $incident->summary, $register->all()));
    }
}

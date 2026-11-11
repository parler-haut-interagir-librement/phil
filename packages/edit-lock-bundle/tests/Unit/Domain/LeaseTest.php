<?php

declare(strict_types=1);

namespace Phil\EditLockBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\Persistence\Mapping\RuntimeReflectionService;
use Phil\EditLockBundle\Domain\Exception\InvalidLease;
use Phil\EditLockBundle\Domain\Model\Lease;
use Phil\EditLockBundle\Domain\ValueObject\Holder;
use Phil\EditLockBundle\Domain\ValueObject\LeaseKey;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Lease::class)]
final class LeaseTest extends TestCase
{
    public function testALeaseIsGrantedRenewedAndPassedOn(): void
    {
        $now = new DateTimeImmutable('2026-11-06 10:00:00.750 America/Montreal');
        $ada = Holder::of('ada', 'Ada');
        $lease = Lease::grant(LeaseKey::of('page', 'b1'), $ada, $now, 30);

        self::assertSame(['page#b1', 'ada', 'Ada', '2026-11-06T15:00:00+00:00', '2026-11-06T15:00:30+00:00', 1], [
            $lease->key()->toString(), $lease->holder()->id, $lease->holder()->name, $lease->acquiredAt()->format('c'), $lease->expiresAt()->format('c'), $lease->version,
        ]);
        self::assertSame([true, false], [$lease->isHeldBy(Holder::of('ada', 'x')), $lease->isHeldBy(Holder::of('bob', 'Ada'))]);
        self::assertSame([true, true, false, false], [
            $lease->isActiveAt($now), $lease->isActiveAt($now->modify('+29 seconds')), $lease->isActiveAt($now->modify('+30 seconds')), $lease->isActiveAt($now->modify('+1 hour')),
        ]);
        self::assertFalse($lease->isActiveAt(new DateTimeImmutable('2026-11-06 15:00:30 UTC')));
        self::assertTrue($lease->isActiveAt(new DateTimeImmutable('2026-11-06 15:00:29 UTC')));
        self::assertSame([30, 1, 0, 0], [$lease->secondsLeftAt($now), $lease->secondsLeftAt($now->modify('+29 seconds')), $lease->secondsLeftAt($now->modify('+30 seconds')), $lease->secondsLeftAt($now->modify('+1 hour'))]);

        $renewed = $lease->renewed(Holder::of('ada', 'Ada L.'), $now->modify('+20 seconds'), 10);
        self::assertSame(['ada', 'Ada L.', '2026-11-06T15:00:00+00:00', '2026-11-06T15:00:30+00:00', 2], [
            $renewed->holder()->id, $renewed->holder()->name, $renewed->acquiredAt()->format('c'), $renewed->expiresAt()->format('c'), $renewed->version,
        ]);

        $passed = $renewed->passedTo(Holder::of('bob', 'Bob'), $now->modify('+1 minute'), 15);
        self::assertSame(['page#b1', 'bob', 'Bob', '2026-11-06T15:01:00+00:00', '2026-11-06T15:01:15+00:00', 3], [
            $passed->key()->toString(), $passed->holder()->id, $passed->holder()->name, $passed->acquiredAt()->format('c'), $passed->expiresAt()->format('c'), $passed->version,
        ]);
        self::assertSame('page', Lease::grant(LeaseKey::of('page'), $ada, $now, 1)->key()->toString());
        self::assertNull(Lease::grant(LeaseKey::of('page'), $ada, $now, 1)->key()->part);
    }

    public function testALeaseIsRestored(): void
    {
        $at = new DateTimeImmutable('2026-11-06 15:00:00.5', new DateTimeZone('UTC'));
        $lease = Lease::restore(LeaseKey::of('page'), Holder::of('ada', 'Ada'), $at, $at, 1);

        self::assertSame(['page', '2026-11-06 15:00:00.000000', '2026-11-06 15:00:00.000000', 1], [$lease->key()->toString(), $lease->acquiredAt()->format('Y-m-d H:i:s.u'), $lease->expiresAt()->format('Y-m-d H:i:s.u'), $lease->version]);

        foreach ([[$at->modify('-1 second'), 1, 'A lease expires'], [$at, 0, 'The version']] as [$expiresAt, $version, $message]) {
            try {
                Lease::restore(LeaseKey::of('page'), Holder::of('ada', 'Ada'), $at, $expiresAt, $version);
                self::fail($message);
            } catch (InvalidLease $invalidLease) {
                self::assertStringStartsWith($message, $invalidLease->getMessage());
            }
        }
    }

    public function testALeaseLastsASecondAtLeast(): void
    {
        $now = new DateTimeImmutable();
        $lease = Lease::grant(LeaseKey::of('page'), Holder::of('ada', 'Ada'), $now, 1);

        foreach ([
            static fn (): Lease => Lease::grant(LeaseKey::of('page'), Holder::of('ada', 'Ada'), $now, 0),
            static fn (): Lease => $lease->renewed(Holder::of('ada', 'Ada'), $now, 0),
            static fn (): Lease => $lease->passedTo(Holder::of('bob', 'Bob'), $now, -1),
        ] as $change) {
            try {
                $change();
                self::fail('A lease of no time.');
            } catch (InvalidLease $invalidLease) {
                self::assertSame('A lease lasts at least one second.', $invalidLease->getMessage());
            }
        }
    }

    public function testTheTable(): void
    {
        $metadata = new ClassMetadata(Lease::class);
        $metadata->initializeReflection(new RuntimeReflectionService());
        new AttributeDriver([\dirname(__DIR__, 3).'/src/Domain/Model'])->loadMetadataForClass(Lease::class, $metadata);
        $columns = [];

        foreach ($metadata->fieldMappings as $field => $mapping) {
            $columns[$field] = [$mapping->columnName, $mapping->type, $mapping->length, $mapping->nullable];
        }

        self::assertSame(['phil_edit_lease', ['resource', 'part'], [
            'resource' => ['resource', 'string', 255, false],
            'part' => ['part', 'string', 64, false],
            'holderId' => ['holder_id', 'string', 255, false],
            'holderName' => ['holder_name', 'string', 255, false],
            'acquiredAt' => ['acquired_at', 'datetime_immutable', null, false],
            'expiresAt' => ['expires_at', 'datetime_immutable', null, false],
            'version' => ['version', 'integer', null, false],
        ]], [$metadata->getTableName(), $metadata->getIdentifierFieldNames(), $columns]);
        self::assertSame([['columns' => ['expires_at']]], array_values($metadata->table['indexes'] ?? []));
    }
}

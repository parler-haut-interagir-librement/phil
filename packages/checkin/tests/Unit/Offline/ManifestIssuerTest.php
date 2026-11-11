<?php

declare(strict_types=1);

namespace Phil\Checkin\Tests\Unit\Offline;

use DateTimeImmutable;
use Phil\Checkin\AdmissionPolicy;
use Phil\Checkin\Attendance;
use Phil\Checkin\CheckIn;
use Phil\Checkin\CheckpointId;
use Phil\Checkin\Credential;
use Phil\Checkin\CredentialCode;
use Phil\Checkin\CredentialStatus;
use Phil\Checkin\DeviceId;
use Phil\Checkin\Direction;
use Phil\Checkin\Exception\InvalidCheckin;
use Phil\Checkin\Exception\ManifestTooLarge;
use Phil\Checkin\Exception\WeakManifestCodes;
use Phil\Checkin\Offline\ManifestEntry;
use Phil\Checkin\Offline\ManifestIssuer;
use Phil\Checkin\Offline\OfflineManifest;
use Phil\Checkin\ScanId;
use Phil\Checkin\ScanOutcome;
use Phil\Checkin\Tests\Unit\Fixture\FixedCredentials;
use Phil\Checkin\Tests\Unit\Fixture\FrozenClock;
use Phil\Checkin\Tests\Unit\Fixture\ScriptedLedger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(ManifestIssuer::class)]
#[CoversClass(OfflineManifest::class)]
#[CoversClass(ManifestEntry::class)]
#[CoversClass(ManifestTooLarge::class)]
#[CoversClass(WeakManifestCodes::class)]
final class ManifestIssuerTest extends TestCase
{
    public function testTheManifestCarriesTheCredentialsThatOpenTheCheckpointUnderFingerprints(): void
    {
        $from = new DateTimeImmutable('2026-11-08 09:00:00 UTC');
        $until = new DateTimeImmutable('2026-11-08 23:00:00 UTC');
        $catalog = new FixedCredentials([
            'CODE-1' => Credential::of('T-1', 'Élise', validFrom: $from, validUntil: $until),
            'CODE-2' => Credential::of('T-2', 'Marc', [CheckpointId::of('stage')]),
            'CODE-3' => Credential::of('T-3', maxEntries: null, status: CredentialStatus::Revoked),
        ]);
        $ledger = new ScriptedLedger();
        $at = new DateTimeImmutable('2026-11-08 09:30:00 UTC');
        $ledger->inner->append(CheckIn::decided(ScanId::of('s1'), Credential::of('T-1'), CheckpointId::of('hall'), Direction::In, ScanOutcome::Admitted, DeviceId::of('d0'), $at, $at, Attendance::none()));
        $clock = new FrozenClock();

        $manifest = new ManifestIssuer($catalog, $ledger, $clock, new AdmissionPolicy(600, false), 3600, minCodeBits: 0)->issue(CheckpointId::of('hall'), DeviceId::of('door-1'));
        $array = $manifest->toArray();

        self::assertSame(32, \strlen($manifest->key));
        self::assertSame($clock->now(), $manifest->issuedAt);
        self::assertSame($clock->now()->getTimestamp() + 3600, $manifest->expiresAt->getTimestamp());
        self::assertSame([['T-1', 'T-3']], $ledger->attendanceCalls);
        self::assertSame(1, $array['format']);
        self::assertSame('hall', $array['checkpoint']);
        self::assertSame('door-1', $array['device']);
        self::assertSame($clock->now()->getTimestamp(), $array['issuedAt']);
        self::assertSame($clock->now()->getTimestamp() + 3600, $array['expiresAt']);
        self::assertSame(sodium_bin2base64($manifest->key, \SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING), $array['key']);
        self::assertSame(['earlySeconds' => 600, 'antiPassback' => false], $array['policy']);
        self::assertSame([
            [CredentialCode::of('CODE-1')->fingerprint($manifest->key), 'T-1', null, $from->getTimestamp(), $until->getTimestamp(), 1, 1, true, false],
            [CredentialCode::of('CODE-3')->fingerprint($manifest->key), 'T-3', null, null, null, null, 0, false, true],
        ], $array['credentials']);
    }

    public function testEachManifestDrawsItsOwnKey(): void
    {
        $issuer = new ManifestIssuer(new FixedCredentials(), new ScriptedLedger(), new FrozenClock());

        self::assertNotSame($issuer->issue(CheckpointId::of('hall'), DeviceId::of('d1'))->key, $issuer->issue(CheckpointId::of('hall'), DeviceId::of('d1'))->key);
    }

    public function testTheLabelsAreCarriedOnlyWhenAskedFor(): void
    {
        $catalog = new FixedCredentials(['CODE-1' => Credential::of('T-1', 'Élise')]);
        $manifest = new ManifestIssuer($catalog, new ScriptedLedger(), new FrozenClock(), labels: true, minCodeBits: 0)->issue(CheckpointId::of('hall'), DeviceId::of('d1'));

        self::assertSame('Élise', $manifest->toArray()['credentials'][0][2]);
        self::assertSame(43_200, $manifest->expiresAt->getTimestamp() - $manifest->issuedAt->getTimestamp());
    }

    public function testTheAttendancesAreReadInChunksAndOncePerCredential(): void
    {
        $credentials = [];

        for ($i = 0; $i < ManifestIssuer::CHUNK; ++$i) {
            $credentials['CODE-'.$i] = Credential::of('T-'.$i);
        }

        $credentials['CODE-X'] = Credential::of('T-Y');
        $credentials['CODE-Y'] = Credential::of('T-Y');
        $credentials['CODE-Z'] = Credential::of('T-0');
        $ledger = new ScriptedLedger();
        $manifest = new ManifestIssuer(new FixedCredentials($credentials), $ledger, new FrozenClock(), minCodeBits: 0)->issue(CheckpointId::of('hall'), DeviceId::of('d1'));

        self::assertSame(500, ManifestIssuer::CHUNK);
        self::assertCount(503, $manifest->entries);
        self::assertCount(2, $ledger->attendanceCalls);
        self::assertCount(500, $ledger->attendanceCalls[0]);
        self::assertSame(['T-Y', 'T-0'], $ledger->attendanceCalls[1]);
    }

    public function testAnEmptyCatalogAsksTheLedgerNothing(): void
    {
        $ledger = new ScriptedLedger();
        $manifest = new ManifestIssuer(new FixedCredentials(['CODE-1' => Credential::of('T-1', checkpoints: [CheckpointId::of('stage')])]), $ledger, new FrozenClock())->issue(CheckpointId::of('hall'), DeviceId::of('d1'));

        self::assertSame([], $manifest->entries);
        self::assertSame([], $ledger->attendanceCalls);
    }

    public function testACatalogOverTheLimitIsRefused(): void
    {
        $catalog = new FixedCredentials(['A' => Credential::of('T-1'), 'B' => Credential::of('T-2'), 'C' => Credential::of('T-3', checkpoints: [CheckpointId::of('stage')])]);

        self::assertCount(2, new ManifestIssuer($catalog, new ScriptedLedger(), new FrozenClock(), limit: 2, minCodeBits: 0)->issue(CheckpointId::of('hall'), DeviceId::of('d1'))->entries);

        $this->expectException(ManifestTooLarge::class);
        $this->expectExceptionMessageIsOrContains('An offline manifest carries at most 1 credentials; the catalog holds more for this checkpoint.');

        new ManifestIssuer($catalog, new ScriptedLedger(), new FrozenClock(), limit: 1, minCodeBits: 0)->issue(CheckpointId::of('hall'), DeviceId::of('d1'));
    }

    public function testAManifestCarriesTenThousandCredentialsByDefault(): void
    {
        $credentials = [];

        for ($i = 0; $i < 10_000; ++$i) {
            $credentials['CODE-'.$i] = Credential::of('T-'.$i);
        }

        $catalog = new FixedCredentials($credentials);
        self::assertCount(10_000, new ManifestIssuer($catalog, new ScriptedLedger(), new FrozenClock(), minCodeBits: 0)->issue(CheckpointId::of('hall'), DeviceId::of('d1'))->entries);

        $catalog->byCode['CODE-X'] = Credential::of('T-X');

        $this->expectException(ManifestTooLarge::class);

        new ManifestIssuer($catalog, new ScriptedLedger(), new FrozenClock(), minCodeBits: 0)->issue(CheckpointId::of('hall'), DeviceId::of('d1'));
    }

    public function testTheBoundsAreKept(): void
    {
        $issuer = new ManifestIssuer(new FixedCredentials(['A' => Credential::of('T-1')]), new ScriptedLedger(), new FrozenClock(), seconds: 60, limit: 1, minCodeBits: 0);
        $manifest = $issuer->issue(CheckpointId::of('hall'), DeviceId::of('d1'));

        self::assertSame(60, $manifest->expiresAt->getTimestamp() - $manifest->issuedAt->getTimestamp());
        self::assertSame(604_800, ManifestIssuer::MAX_SECONDS);
        self::assertSame(100_000, ManifestIssuer::MAX_LIMIT);
        new ManifestIssuer(new FixedCredentials(), new ScriptedLedger(), new FrozenClock(), seconds: 604_800, limit: 100_000, minCodeBits: 128);
    }

    public function testAManifestCarriesOnlyCodesOfSixtyFourBitsByDefault(): void
    {
        $catalog = new FixedCredentials(['0123456789abcdef' => Credential::of('T-1'), '18446744073709551615' => Credential::of('T-2')]);

        self::assertCount(2, new ManifestIssuer($catalog, new ScriptedLedger(), new FrozenClock())->issue(CheckpointId::of('hall'), DeviceId::of('d1'))->entries);

        $catalog->byCode['01234567'] = Credential::of('T-3');

        try {
            new ManifestIssuer($catalog, new ScriptedLedger(), new FrozenClock())->issue(CheckpointId::of('hall'), DeviceId::of('d1'));
            self::fail('A short code is refused.');
        } catch (WeakManifestCodes $weakManifestCodes) {
            self::assertSame('An offline manifest carries codes of at least 64 bits, whose fingerprints cannot be tried one by one; the catalog holds a weaker one for this checkpoint.', $weakManifestCodes->getMessage());
            self::assertStringNotContainsString('01234567', $weakManifestCodes->getMessage());
        }
    }

    public function testTheStrengthAskedOfTheCodesIsChosen(): void
    {
        $catalog = new FixedCredentials(['0123456789abcde' => Credential::of('T-1'), 'C' => Credential::of('T-2', checkpoints: [CheckpointId::of('stage')])]);

        self::assertCount(1, new ManifestIssuer($catalog, new ScriptedLedger(), new FrozenClock(), minCodeBits: 60)->issue(CheckpointId::of('hall'), DeviceId::of('d1'))->entries);

        $this->expectException(WeakManifestCodes::class);
        $this->expectExceptionMessageIsOrContains('at least 61 bits');

        new ManifestIssuer($catalog, new ScriptedLedger(), new FrozenClock(), minCodeBits: 61)->issue(CheckpointId::of('hall'), DeviceId::of('d1'));
    }

    #[TestWith([-1])]
    #[TestWith([129])]
    public function testAnInvalidStrengthIsRefused(int $bits): void
    {
        $this->expectException(InvalidCheckin::class);
        $this->expectExceptionMessageIsOrContains('An offline manifest asks its codes 0 to 128 bits.');

        new ManifestIssuer(new FixedCredentials(), new ScriptedLedger(), new FrozenClock(), minCodeBits: $bits);
    }

    #[TestWith([59, 10, 'An offline manifest is valid 60 to 604800 seconds.'])]
    #[TestWith([604_801, 10, 'An offline manifest is valid 60 to 604800 seconds.'])]
    #[TestWith([60, 0, 'An offline manifest carries 1 to 100000 credentials.'])]
    #[TestWith([60, 100_001, 'An offline manifest carries 1 to 100000 credentials.'])]
    public function testInvalidBoundsAreRefused(int $seconds, int $limit, string $message): void
    {
        $this->expectException(InvalidCheckin::class);
        $this->expectExceptionMessageIsOrContains($message);

        new ManifestIssuer(new FixedCredentials(), new ScriptedLedger(), new FrozenClock(), seconds: $seconds, limit: $limit);
    }
}

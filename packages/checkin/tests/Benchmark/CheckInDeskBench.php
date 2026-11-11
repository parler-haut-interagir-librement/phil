<?php

declare(strict_types=1);

namespace Phil\Checkin\Tests\Benchmark;

use Phil\Checkin\AdmissionPolicy;
use Phil\Checkin\Attendance;
use Phil\Checkin\CheckInDesk;
use Phil\Checkin\CheckpointId;
use Phil\Checkin\Credential;
use Phil\Checkin\CredentialCode;
use Phil\Checkin\DeviceId;
use Phil\Checkin\Direction;
use Phil\Checkin\InMemoryCheckInLedger;
use Phil\Checkin\Offline\ManifestIssuer;
use Phil\Checkin\Offline\OfflineBatch;
use Phil\Checkin\Tests\Unit\Fixture\FixedCredentials;
use Phil\Checkin\Tests\Unit\Fixture\FrozenClock;

/**
 * What a door costs: a scan decided and recorded, the decision alone, a code fingerprinted; what a device costs when
 * it goes offline, a manifest of 10 000 credentials issued; and what it costs back online, a batch of 500 scans read.
 */
final readonly class CheckInDeskBench
{
    private CheckInDesk $desk;

    private ManifestIssuer $issuer;

    private AdmissionPolicy $policy;

    private Credential $credential;

    private CheckpointId $hall;

    private DeviceId $device;

    private FrozenClock $clock;

    /** @var list<array<string, int|string>> */
    private array $batch;

    public function __construct()
    {
        $credentials = [];

        for ($i = 0; $i < 10_000; ++$i) {
            $credentials['CODE-'.$i] = Credential::of('T-'.$i, maxEntries: null);
        }

        $catalog = new FixedCredentials($credentials);
        $this->clock = new FrozenClock();
        $this->policy = new AdmissionPolicy(antiPassback: false);
        $this->desk = new CheckInDesk($catalog, new InMemoryCheckInLedger(), $this->clock, $this->policy);
        $this->issuer = new ManifestIssuer($catalog, new InMemoryCheckInLedger(), $this->clock, minCodeBits: 0);
        $this->credential = $credentials['CODE-42'];
        $this->hall = CheckpointId::of('hall');
        $this->device = DeviceId::of('door-1');
        $this->batch = array_fill(0, 500, ['id' => 's1', 'credential' => 'T-1', 'direction' => 'in', 'outcome' => 'admitted', 'scannedAt' => 1_794_132_000]);
    }

    public function benchScan(): void
    {
        $this->desk->scan(CredentialCode::of('CODE-42'), $this->hall, $this->device);
    }

    public function benchDecide(): void
    {
        $this->policy->decide($this->credential, Attendance::none(), $this->hall, Direction::In, $this->clock->now());
    }

    public function benchFingerprint(): void
    {
        CredentialCode::of('CODE-42')->fingerprint('0123456789abcdef0123456789abcdef');
    }

    public function benchManifestOfTenThousand(): void
    {
        $this->issuer->issue($this->hall, $this->device);
    }

    public function benchBatchOfFiveHundred(): void
    {
        OfflineBatch::read($this->batch);
    }
}

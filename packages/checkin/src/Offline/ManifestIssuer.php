<?php

declare(strict_types=1);

namespace Phil\Checkin\Offline;

use Phil\Checkin\AdmissionPolicy;
use Phil\Checkin\Attendance;
use Phil\Checkin\CatalogEntry;
use Phil\Checkin\CheckpointId;
use Phil\Checkin\Contract\CheckInLedger;
use Phil\Checkin\Contract\CredentialCatalog;
use Phil\Checkin\DeviceId;
use Phil\Checkin\Exception\InvalidCheckin;
use Phil\Checkin\Exception\ManifestTooLarge;
use Phil\Checkin\Exception\WeakManifestCodes;
use Psr\Clock\ClockInterface;

/**
 * Issues the offline manifest of a checkpoint for a device: the credentials of the catalog that open it, at most a
 * limit of them, with their attendance read from the ledger in chunks of {@see self::CHUNK}, valid for a number of
 * seconds. Each manifest draws its own key, so that two devices never hold the same fingerprints.
 *
 * The manifest carries its key, so a fingerprint keeps a code only as well as the code's own strength: whoever holds
 * the manifest can try every code of a shape against it. The issuer refuses a code whose
 * {@see \Phil\Checkin\CredentialCode::estimatedBits()} is under a minimum, 64 bits by default — 16 random hexadecimal
 * digits, 11 characters of URL-safe base64, 20 digits.
 */
final readonly class ManifestIssuer
{
    public const int CHUNK = 500;

    public const int MAX_SECONDS = 604_800;

    public const int MAX_LIMIT = 100_000;

    public const int MAX_CODE_BITS = 128;

    /**
     * @param int  $seconds     how long a manifest may be used, from 60 seconds to a week
     * @param int  $limit       how many credentials a manifest carries at most, from 1 to 100 000
     * @param bool $labels      whether the manifest carries the labels of the credentials, such as the holders' names
     * @param int  $minCodeBits the strength a code needs to be carried, from 0, which takes any code, to 128
     *
     * @throws InvalidCheckin
     */
    public function __construct(
        private CredentialCatalog $catalog,
        private CheckInLedger $ledger,
        private ClockInterface $clock,
        private AdmissionPolicy $policy = new AdmissionPolicy(),
        private int $seconds = 43_200,
        private int $limit = 10_000,
        private bool $labels = false,
        private int $minCodeBits = 64,
    ) {
        if ($seconds < 60 || $seconds > self::MAX_SECONDS) {
            throw InvalidCheckin::because(\sprintf('An offline manifest is valid 60 to %d seconds.', self::MAX_SECONDS));
        }

        if ($limit < 1 || $limit > self::MAX_LIMIT) {
            throw InvalidCheckin::because(\sprintf('An offline manifest carries 1 to %d credentials.', self::MAX_LIMIT));
        }

        if ($minCodeBits < 0 || $minCodeBits > self::MAX_CODE_BITS) {
            throw InvalidCheckin::because(\sprintf('An offline manifest asks its codes 0 to %d bits.', self::MAX_CODE_BITS));
        }
    }

    /**
     * @throws ManifestTooLarge  when the catalog holds more credentials for the checkpoint than the limit
     * @throws WeakManifestCodes when the catalog holds a code for the checkpoint under the strength asked
     */
    public function issue(CheckpointId $checkpoint, DeviceId $device): OfflineManifest
    {
        $now = $this->clock->now();
        $key = random_bytes(32);

        /** @var list<CatalogEntry> $chunk */
        $chunk = [];
        $entries = [];
        $count = 0;

        foreach ($this->catalog->forCheckpoint($checkpoint) as $entry) {
            if (!$entry->credential->opens($checkpoint)) {
                continue;
            }

            if (++$count > $this->limit) {
                throw ManifestTooLarge::over($this->limit);
            }

            if ($entry->code->estimatedBits() < $this->minCodeBits) {
                throw WeakManifestCodes::under($this->minCodeBits);
            }

            $chunk[] = $entry;

            if (self::CHUNK === \count($chunk)) {
                array_push($entries, ...$this->entries($chunk, $key));
                $chunk = [];
            }
        }

        array_push($entries, ...$this->entries($chunk, $key));

        return new OfflineManifest($checkpoint, $device, $now, $now->modify(\sprintf('+%d seconds', $this->seconds)), $key, $this->policy, $entries);
    }

    /**
     * @param list<CatalogEntry> $chunk
     *
     * @return list<ManifestEntry>
     */
    private function entries(array $chunk, string $key): array
    {
        if ([] === $chunk) {
            return [];
        }

        $ids = array_values(array_unique(array_map(static fn (CatalogEntry $entry): string => $entry->credential->id, $chunk)));
        $attendances = $this->ledger->attendances($ids);

        return array_map(fn (CatalogEntry $entry): ManifestEntry => new ManifestEntry(
            $entry->code->fingerprint($key),
            $entry->credential,
            $attendances[$entry->credential->id] ?? Attendance::none(),
            $this->labels,
        ), $chunk);
    }
}

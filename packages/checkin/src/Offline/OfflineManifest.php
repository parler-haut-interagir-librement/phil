<?php

declare(strict_types=1);

namespace Phil\Checkin\Offline;

use DateTimeImmutable;
use Phil\Checkin\AdmissionPolicy;
use Phil\Checkin\CheckpointId;
use Phil\Checkin\DeviceId;
use SensitiveParameter;

/**
 * What a device downloads to keep checking in at one checkpoint without the network: the credentials that open it,
 * each under the HMAC-SHA256 fingerprint of a code with a key drawn for this manifest alone, their attendance when it
 * was issued, the policy, and until when it may be used.
 *
 * The codes themselves never leave the server, but the key travels with the fingerprints: a device that is lost gives
 * away every code that can be guessed, since one can compute the fingerprint of each guess and look it up. A manifest
 * keeps the codes only as well as they are long and random — {@see ManifestIssuer} refuses codes under 64 bits by
 * default — so offline devices want long random payloads, never numbers typed at the desk. Labels are left out unless the application asks for them, so that a lost device gives away no
 * names either.
 */
final readonly class OfflineManifest
{
    public const int FORMAT = 1;

    /**
     * @param list<ManifestEntry> $entries
     */
    public function __construct(
        public CheckpointId $checkpoint,
        public DeviceId $device,
        public DateTimeImmutable $issuedAt,
        public DateTimeImmutable $expiresAt,
        #[SensitiveParameter]
        public string $key,
        public AdmissionPolicy $policy,
        public array $entries,
    ) {
    }

    /**
     * The manifest as the device reads it, the key in URL-safe base64 without padding.
     *
     * @return array{format: int, checkpoint: string, device: string, issuedAt: int, expiresAt: int, key: string, policy: array{earlySeconds: int, antiPassback: bool}, credentials: list<array{string, string, ?string, ?int, ?int, ?int, int, bool, bool}>}
     */
    public function toArray(): array
    {
        return [
            'format' => self::FORMAT,
            'checkpoint' => $this->checkpoint->value,
            'device' => $this->device->value,
            'issuedAt' => $this->issuedAt->getTimestamp(),
            'expiresAt' => $this->expiresAt->getTimestamp(),
            'key' => sodium_bin2base64($this->key, \SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING),
            'policy' => ['earlySeconds' => $this->policy->earlySeconds, 'antiPassback' => $this->policy->antiPassback],
            'credentials' => array_map(static fn (ManifestEntry $entry): array => $entry->toArray(), $this->entries),
        ];
    }
}

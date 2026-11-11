<?php

declare(strict_types=1);

namespace Phil\Checkin\Offline;

use Phil\Checkin\Attendance;
use Phil\Checkin\Credential;
use Phil\Checkin\CredentialStatus;

/**
 * One credential of an offline manifest: the fingerprint of one of its codes, what the device needs to decide its
 * scans, and where it stood when the manifest was issued.
 */
final readonly class ManifestEntry
{
    public function __construct(
        public string $fingerprint,
        public Credential $credential,
        public Attendance $attendance,
        public bool $withLabel,
    ) {
    }

    /**
     * The entry as the device reads it, a list in this order: fingerprint, credential identifier, label or null,
     * opening and closing as Unix timestamps or null, maximum entries or null, entries used, inside, revoked.
     *
     * @return array{string, string, ?string, ?int, ?int, ?int, int, bool, bool}
     */
    public function toArray(): array
    {
        return [
            $this->fingerprint,
            $this->credential->id,
            $this->withLabel ? $this->credential->label : null,
            $this->credential->validFrom?->getTimestamp(),
            $this->credential->validUntil?->getTimestamp(),
            $this->credential->maxEntries,
            $this->attendance->entries,
            $this->attendance->inside,
            CredentialStatus::Revoked === $this->credential->status,
        ];
    }
}

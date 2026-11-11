<?php

declare(strict_types=1);

namespace Phil\Checkin\Offline;

use DateTimeImmutable;
use Phil\Checkin\Credential;
use Phil\Checkin\Direction;
use Phil\Checkin\Exception\InvalidCheckin;
use Phil\Checkin\ScanId;
use Phil\Checkin\ScanOutcome;

/**
 * One scan a device decided offline, from its manifest: the credential it found by fingerprint, the direction, what
 * the device decided and when, by the device's clock.
 */
final readonly class OfflineScan
{
    private function __construct(
        public ScanId $id,
        public string $credentialId,
        public Direction $direction,
        public ScanOutcome $outcome,
        public DateTimeImmutable $scannedAt,
    ) {
    }

    /**
     * @throws InvalidCheckin
     */
    public static function of(ScanId $id, string $credentialId, Direction $direction, ScanOutcome $outcome, DateTimeImmutable $scannedAt): self
    {
        if (1 !== preg_match(Credential::ID_PATTERN, $credentialId)) {
            throw InvalidCheckin::because(\sprintf('A credential identifier is 1 to %d printable ASCII characters.', Credential::MAX_ID_LENGTH));
        }

        if (\in_array($outcome, [ScanOutcome::Unknown, ScanOutcome::Collision, ScanOutcome::WrongCheckpoint], true)) {
            throw InvalidCheckin::because('A scan made offline is of a credential of the manifest, which opens the checkpoint.');
        }

        if ((ScanOutcome::Admitted === $outcome && Direction::In !== $direction) || (ScanOutcome::CheckedOut === $outcome && Direction::Out !== $direction)) {
            throw InvalidCheckin::because('An admission is an entry and a check-out an exit.');
        }

        return new self($id, $credentialId, $direction, $outcome, $scannedAt);
    }

    /**
     * A scan as the device sends it: `{"id", "credential", "direction", "outcome", "scannedAt"}`, the instant as a
     * Unix timestamp.
     *
     * @param array<array-key, mixed> $scan
     *
     * @throws InvalidCheckin
     */
    public static function fromArray(array $scan): self
    {
        $id = $scan['id'] ?? null;
        $credential = $scan['credential'] ?? null;
        $direction = $scan['direction'] ?? null;
        $outcome = $scan['outcome'] ?? null;
        $scannedAt = $scan['scannedAt'] ?? null;

        if (!\is_string($id) || !\is_string($credential) || !\is_string($direction) || !\is_string($outcome) || !\is_int($scannedAt) || $scannedAt < 0) {
            throw InvalidCheckin::because('A scan has an "id", a "credential", a "direction" and an "outcome", all texts, and a "scannedAt" timestamp.');
        }

        return self::of(
            ScanId::of($id),
            $credential,
            Direction::tryFrom($direction) ?? throw InvalidCheckin::because('A direction is "in" or "out".'),
            ScanOutcome::tryFrom($outcome) ?? throw InvalidCheckin::because('The outcome is not one of the library.'),
            new DateTimeImmutable('@'.$scannedAt),
        );
    }
}

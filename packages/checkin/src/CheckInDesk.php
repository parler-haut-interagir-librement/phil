<?php

declare(strict_types=1);

namespace Phil\Checkin;

use Phil\Checkin\Contract\CheckInLedger;
use Phil\Checkin\Contract\CredentialResolver;
use Phil\Checkin\Exception\LedgerContention;
use Psr\Clock\ClockInterface;

/**
 * The online check-in: a code read at a checkpoint is resolved to its credential, decided by the policy on the
 * credential's attendance and recorded in the ledger, refusals included, under the scan's identifier.
 *
 * When another device records a scan of the same credential between the read of its attendance and the record, the
 * desk decides again on the new attendance, up to {@see self::ATTEMPTS} times. A scan identifier already recorded is
 * answered with its first decision when the scan is the same — the code resolves to the credential recorded, at the
 * same checkpoint, from the same device, in the same direction — and as a collision otherwise, which gives away
 * neither the decision nor the check-in recorded. The desk keeps nothing between scans: it is safe to share across the requests of
 * a long-running worker.
 */
final readonly class CheckInDesk
{
    public const int ATTEMPTS = 3;

    public function __construct(
        private CredentialResolver $resolver,
        private CheckInLedger $ledger,
        private ClockInterface $clock,
        private AdmissionPolicy $policy = new AdmissionPolicy(),
    ) {
    }

    /**
     * @throws LedgerContention when every attempt met another scan of the credential recorded first
     */
    public function scan(CredentialCode $code, CheckpointId $checkpoint, DeviceId $device, Direction $direction = Direction::In, ?ScanId $id = null): ScanResult
    {
        $id ??= ScanId::generate();
        $credential = $this->resolver->resolve($code);
        $known = $this->ledger->find($id);

        if ($known instanceof CheckIn) {
            return $this->known($known, $credential, $checkpoint, $device, $direction);
        }

        if (!$credential instanceof Credential) {
            return ScanResult::unknown();
        }

        for ($attempt = 0; $attempt < self::ATTEMPTS; ++$attempt) {
            $seen = $this->ledger->attendance($credential->id);
            $now = $this->clock->now();
            $outcome = $this->policy->decide($credential, $seen, $checkpoint, $direction, $now);
            $checkIn = CheckIn::decided($id, $credential, $checkpoint, $direction, $outcome, $device, $now, $now, $seen);

            if ($this->ledger->append($checkIn)) {
                return ScanResult::recorded($credential, $seen->after($checkIn), $checkIn);
            }

            $known = $this->ledger->find($id);

            if ($known instanceof CheckIn) {
                return $this->known($known, $credential, $checkpoint, $device, $direction);
            }
        }

        throw LedgerContention::after(self::ATTEMPTS);
    }

    /**
     * The answer to a scan whose identifier is recorded: replayed when it is the same scan, a collision otherwise.
     */
    private function known(CheckIn $known, ?Credential $credential, CheckpointId $checkpoint, DeviceId $device, Direction $direction): ScanResult
    {
        if ($credential?->id !== $known->credentialId || $checkpoint->value !== $known->checkpoint->value || $device->value !== $known->device->value || $direction !== $known->direction) {
            return ScanResult::collided();
        }

        return ScanResult::replayed($known, $credential, $this->ledger->attendance($known->credentialId));
    }
}

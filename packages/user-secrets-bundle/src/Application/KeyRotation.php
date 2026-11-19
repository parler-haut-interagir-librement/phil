<?php

declare(strict_types=1);

namespace Phil\UserSecretsBundle\Application;

use Phil\UserSecretsBundle\Application\Audit\AuditTrail;
use Phil\UserSecretsBundle\Application\Audit\SecretEventKind;
use Phil\UserSecretsBundle\Application\Encryption\Envelope;
use Phil\UserSecretsBundle\Application\Exception\SealingFailed;
use Phil\UserSecretsBundle\Application\Exception\UnknownKey;
use Phil\UserSecretsBundle\Application\Exception\UnreadableSecret;
use Phil\UserSecretsBundle\Domain\Contract\StoredSecretRepository;
use Phil\UserSecretsBundle\Domain\Encryption\SealedFormat;
use Phil\UserSecretsBundle\Domain\Exception\InvalidSecret;

/**
 * Seals again under the current key and in the current format every secret another master key sealed, or that rests
 * in an earlier format, batch by batch, in one pass by owner and name. Each value is written by a compare-and-swap on
 * the sealed value it replaces: a row another process stored, refreshed or revoked meanwhile is left as that process
 * made it, never written over. A value that does not open is named in the report and left as it is; the pass goes on.
 * Once {@see self::remaining()} is empty, the KMS can retire the old master key.
 */
final readonly class KeyRotation
{
    public function __construct(
        private StoredSecretRepository $secrets,
        private Envelope $envelope,
        private AuditTrail $audit,
    ) {
    }

    /**
     * @throws InvalidSecret when the batch size is below 1
     * @throws UnknownKey    when the KMS has no current master key
     * @throws SealingFailed when the KMS does not seal a value again
     */
    public function rotate(int $batchSize): RotationReport
    {
        if ($batchSize < 1) {
            throw InvalidSecret::because('A rotation batch holds at least one secret.');
        }

        $resealed = 0;
        $skipped = 0;
        $unreadable = [];
        $after = null;

        do {
            $batch = $this->secrets->toReseal($this->envelope->currentKey(), SealedFormat::current(), $batchSize, $after);

            foreach ($batch as $secret) {
                try {
                    $sealed = $this->envelope->reseal($secret);
                } catch (UnreadableSecret) {
                    $unreadable[] = ['owner' => $secret->owner(), 'name' => $secret->name()];

                    continue;
                }

                $previous = $secret->sealed();
                $secret->rekey($sealed);

                if (!$this->secrets->replace($secret, $previous)) {
                    ++$skipped;

                    continue;
                }

                $this->audit->record(SecretEventKind::Rekeyed, $secret->owner(), $secret->name());
                ++$resealed;
            }

            // The next batch starts past this one, whatever became of its secrets.
            $after = array_last($batch);
        } while ([] !== $batch);

        return new RotationReport($resealed, $skipped, $unreadable);
    }

    /**
     * @return array<string, int> how many secrets are still to be sealed again, by the key that sealed them, the keys
     *                            in order; empty once none is left
     */
    public function remaining(): array
    {
        return $this->secrets->countToReseal($this->envelope->currentKey(), SealedFormat::current());
    }
}

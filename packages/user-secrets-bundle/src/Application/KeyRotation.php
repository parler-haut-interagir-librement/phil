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
 * in an earlier format, batch by batch; once it has run, the KMS can retire the old master key. Running it again re-seals nothing more.
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
     * @return int how many secrets were re-sealed
     *
     * @throws InvalidSecret    when the batch size is below 1
     * @throws UnknownKey       when the KMS has no current master key
     * @throws UnreadableSecret when a ciphertext does not open, or the KMS does not open it
     * @throws SealingFailed    when the KMS does not seal it again
     */
    public function rotate(int $batchSize): int
    {
        if ($batchSize < 1) {
            throw InvalidSecret::because('A rotation batch holds at least one secret.');
        }

        $count = 0;

        do {
            $batch = $this->secrets->toReseal($this->envelope->currentKey(), SealedFormat::current(), $batchSize);

            foreach ($batch as $secret) {
                $secret->rekey($this->envelope->reseal($secret));
            }

            $this->secrets->save(...$batch);

            foreach ($batch as $secret) {
                $this->audit->record(SecretEventKind::Rekeyed, $secret->owner(), $secret->name());
            }

            $count += \count($batch);
        } while ([] !== $batch);

        return $count;
    }
}

<?php

declare(strict_types=1);

namespace Phil\CheckinBundle\Infrastructure\Rules;

use Override;
use Phil\Checkin\CheckpointId;
use Phil\Checkin\Contract\CredentialCatalog;
use Phil\Checkin\Contract\CredentialResolver;
use Phil\Checkin\Credential;
use Phil\Checkin\CredentialCode;

/**
 * The credentials of the application as one checkpoint's rule sees them: a credential the rule refuses no longer opens
 * the checkpoint, so that the admission policy answers `wrong_checkpoint` and the ledger records the refusal like any
 * other, and the offline manifest of the checkpoint leaves it out. A credential found by its identifier, to record a
 * scan made offline, is the catalog's own: the device decided on the manifest, which the rule had already filtered,
 * and a check-out recorded after the rule closes the door is not reported as a conflict.
 *
 * Built for one scan or one manifest, it keeps nothing between them.
 */
final readonly class RuledCredentials implements CredentialResolver, CredentialCatalog
{
    /** The checkpoint a refused credential is moved to, never one a desk scans at. */
    public const string CLOSED = 'phil-checkin.closed';

    public function __construct(
        private CheckpointRules $rules,
        private CheckpointId $checkpoint,
        private ?CredentialResolver $resolver = null,
        private ?CredentialCatalog $catalog = null,
    ) {
    }

    #[Override]
    public function resolve(CredentialCode $code): ?Credential
    {
        $credential = $this->resolver?->resolve($code);

        return $credential instanceof Credential ? $this->ruled($credential) : null;
    }

    /**
     * The catalog's entries for a checkpoint, without those its rule refuses.
     */
    #[Override]
    public function forCheckpoint(CheckpointId $checkpoint): iterable
    {
        foreach ($this->catalog?->forCheckpoint($checkpoint) ?? [] as $entry) {
            if ($this->rules->admits($checkpoint, $entry->credential)) {
                yield $entry;
            }
        }
    }

    #[Override]
    public function find(string $credentialId): ?Credential
    {
        return $this->catalog?->find($credentialId);
    }

    /**
     * The credential as it is when the rule admits it, and otherwise the same credential opening only {@see self::CLOSED}.
     */
    private function ruled(Credential $credential): Credential
    {
        if (!$credential->opens($this->checkpoint) || $this->rules->admits($this->checkpoint, $credential)) {
            return $credential;
        }

        return Credential::of(
            $credential->id,
            $credential->label,
            [CheckpointId::of(self::CLOSED === $this->checkpoint->value ? self::CLOSED.'-' : self::CLOSED)],
            $credential->validFrom,
            $credential->validUntil,
            $credential->maxEntries,
            $credential->status,
        );
    }
}

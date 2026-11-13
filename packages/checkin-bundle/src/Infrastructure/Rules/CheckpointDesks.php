<?php

declare(strict_types=1);

namespace Phil\CheckinBundle\Infrastructure\Rules;

use Phil\Checkin\AdmissionPolicy;
use Phil\Checkin\CheckInDesk;
use Phil\Checkin\CheckpointId;
use Phil\Checkin\Contract\CheckInLedger;
use Phil\Checkin\Contract\CredentialCatalog;
use Phil\Checkin\Contract\CredentialResolver;
use Phil\Checkin\Direction;
use Phil\Checkin\Exception\InvalidCheckin;
use Phil\Checkin\Offline\ManifestIssuer;
use Psr\Clock\ClockInterface;

/**
 * The desk and the manifest issuer of one checkpoint, with its admission rule: an entry is decided on the credential
 * as the rule sees it, an exit on the credential itself — nobody is held inside because a rule closed the door —, and
 * a manifest carries only the credentials the rule admits when it is issued.
 *
 * Each call builds its desk or issuer from the shared services and keeps nothing: it is safe to share across the
 * requests of a long-running worker.
 */
final readonly class CheckpointDesks
{
    /**
     * @param int  $manifestSeconds how long a manifest may be used
     * @param int  $maxCredentials  how many credentials a manifest carries at most
     * @param bool $labels          whether a manifest carries the credentials' labels
     * @param int  $minCodeBits     the strength a code needs to be carried by a manifest
     */
    public function __construct(
        private CredentialResolver $resolver,
        private CheckInLedger $ledger,
        private ClockInterface $clock,
        private AdmissionPolicy $policy,
        private CheckpointRules $rules,
        private ?CredentialCatalog $catalog = null,
        private int $manifestSeconds = 43_200,
        private int $maxCredentials = 10_000,
        private bool $labels = false,
        private int $minCodeBits = 64,
    ) {
    }

    public function desk(CheckpointId $checkpoint, Direction $direction = Direction::In): CheckInDesk
    {
        $resolver = Direction::In === $direction && $this->rules->has($checkpoint)
            ? new RuledCredentials($this->rules, $checkpoint, $this->resolver)
            : $this->resolver;

        return new CheckInDesk($resolver, $this->ledger, $this->clock, $this->policy);
    }

    /**
     * The manifest issuer of a checkpoint, or null when the application has no catalog and checks in online only.
     *
     * @throws InvalidCheckin when the bounds of the manifest are out of range
     */
    public function manifests(CheckpointId $checkpoint): ?ManifestIssuer
    {
        if (!$this->catalog instanceof CredentialCatalog) {
            return null;
        }

        $catalog = $this->rules->has($checkpoint) ? new RuledCredentials($this->rules, $checkpoint, catalog: $this->catalog) : $this->catalog;

        return new ManifestIssuer($catalog, $this->ledger, $this->clock, $this->policy, $this->manifestSeconds, $this->maxCredentials, $this->labels, $this->minCodeBits);
    }
}

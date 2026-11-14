<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Infrastructure\Database;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Override;
use Phil\PrivacyBundle\Application\Contract\ConsentProofs;
use Phil\PrivacyBundle\Application\Contract\RetentionPurger;
use Phil\PrivacyBundle\Domain\Consent\ConsentChoice;
use Phil\PrivacyBundle\Domain\Retention\PurgeOrder;

/**
 * The proofs of consent in `phil_privacy_consent_proof`: one insert per choice, never an update. It answers for the
 * store of the same name in a retention schedule.
 */
final readonly class DbalConsentProofs implements ConsentProofs, RetentionPurger
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Override]
    public function record(ConsentChoice $choice): void
    {
        $this->connection->insert(PrivacyTables::PROOFS, [
            'id' => $choice->id,
            'version' => $choice->version,
            'granted' => json_encode($choice->granted, \JSON_THROW_ON_ERROR),
            'global_privacy_control' => $choice->globalPrivacyControl,
            'decided_at' => UtcMoments::text($this->connection, $choice->decidedAt),
        ], ['global_privacy_control' => ParameterType::BOOLEAN]);
    }

    #[Override]
    public function store(): string
    {
        return PrivacyTables::PROOFS;
    }

    #[Override]
    public function purge(PurgeOrder $order): int
    {
        return (int) $this->connection->executeStatement(
            'DELETE FROM '.PrivacyTables::PROOFS.' WHERE decided_at < ?',
            [UtcMoments::text($this->connection, $order->cutoff)],
        );
    }
}

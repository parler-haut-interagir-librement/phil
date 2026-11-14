<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Infrastructure\Log;

use DateTimeInterface;
use Override;
use Phil\PrivacyBundle\Application\Contract\ConsentProofs;
use Phil\PrivacyBundle\Domain\Consent\ConsentChoice;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * The proofs as log lines, for an application without a database: they last as long as its logs do, and are not kept
 * at all by an application without a logger.
 */
final readonly class LoggedConsentProofs implements ConsentProofs
{
    private LoggerInterface $logger;

    public function __construct(?LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? new NullLogger();
    }

    #[Override]
    public function record(ConsentChoice $choice): void
    {
        $this->logger->info('Consent {id} recorded on version {version}.', [
            'id' => $choice->id,
            'version' => $choice->version,
            'granted' => $choice->granted,
            'global_privacy_control' => $choice->globalPrivacyControl,
            'decided_at' => $choice->decidedAt->format(DateTimeInterface::ATOM),
        ]);
    }
}

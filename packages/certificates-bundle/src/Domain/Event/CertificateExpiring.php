<?php

declare(strict_types=1);

namespace Phil\CertificatesBundle\Domain\Event;

/**
 * A certificate whose end comes within one of the reminder's thresholds, neither revoked nor renewed: the application
 * reminds its holder, by the means it chooses. It is dispatched once for each threshold reached, and carries
 * identifiers only — the certificate's, its verification code and its course's slug — and the whole days left.
 */
final readonly class CertificateExpiring
{
    public function __construct(
        public string $certificateId,
        public string $code,
        public string $course,
        public int $daysLeft,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace Phil\CertificatesBundle\Domain\Event;

/**
 * A certificate awarded by a recertification and stored, after its `CertificateAwarded`. It carries identifiers only —
 * the new certificate's, its verification code, its course's slug and the identifier of the certificate it renews —.
 */
final readonly class CertificateRenewed
{
    public function __construct(
        public string $certificateId,
        public string $code,
        public string $course,
        public string $renewedCertificateId,
    ) {
    }
}

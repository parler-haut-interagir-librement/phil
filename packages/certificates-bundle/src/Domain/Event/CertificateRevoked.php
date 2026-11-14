<?php

declare(strict_types=1);

namespace Phil\CertificatesBundle\Domain\Event;

/**
 * A certificate revoked and stored so. It carries identifiers only — the certificate's, its verification code and its
 * course's slug —: the reason and the moment are read from the repository.
 */
final readonly class CertificateRevoked
{
    public function __construct(
        public string $certificateId,
        public string $code,
        public string $course,
    ) {
    }
}

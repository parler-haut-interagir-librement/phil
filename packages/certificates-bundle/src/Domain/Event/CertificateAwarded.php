<?php

declare(strict_types=1);

namespace Phil\CertificatesBundle\Domain\Event;

/**
 * A certificate awarded and stored. It carries identifiers only — the certificate's, its verification code and its
 * course's slug —: a listener that needs more, to send the PDF for example, reads the certificate from the repository.
 */
final readonly class CertificateAwarded
{
    public function __construct(
        public string $certificateId,
        public string $code,
        public string $course,
    ) {
    }
}

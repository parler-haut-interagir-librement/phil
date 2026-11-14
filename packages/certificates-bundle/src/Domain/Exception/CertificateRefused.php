<?php

declare(strict_types=1);

namespace Phil\CertificatesBundle\Domain\Exception;

use DomainException;

/**
 * An award or a revocation refused: a course that is unknown or retired, criteria not met, a certificate that is
 * unknown or already revoked, a status list that is full.
 */
final class CertificateRefused extends DomainException implements CertificatesException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}

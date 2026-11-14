<?php

declare(strict_types=1);

namespace Phil\CertificatesBundle\Domain\Status;

/**
 * Where a certificate stands at a given moment: valid, past the end of its validity, or revoked by its issuer.
 */
enum CertificateStatus: string
{
    case Valid = 'valid';
    case Expired = 'expired';
    case Revoked = 'revoked';
}

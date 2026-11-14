<?php

declare(strict_types=1);

namespace Phil\CertificatesBundle\Application\Contract;

use Phil\CertificatesBundle\Domain\ValueObject\VerificationCode;
use Phil\OpenBadges\Status\StatusListEntry;

/**
 * The absolute URLs a certificate points to: its course's page in the register, which identifies the credential's
 * achievement, its own page, which its QR code opens, and its entry in the issuer's status lists.
 */
interface CertificateUrls
{
    public function course(string $slug): string;

    public function certificate(VerificationCode $code): string;

    public function statusEntry(string $list, int $index): StatusListEntry;
}

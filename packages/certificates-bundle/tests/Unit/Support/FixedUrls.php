<?php

declare(strict_types=1);

namespace Phil\CertificatesBundle\Tests\Unit\Support;

use Override;
use Phil\CertificatesBundle\Application\Contract\CertificateUrls;
use Phil\CertificatesBundle\Domain\ValueObject\VerificationCode;
use Phil\OpenBadges\Status\StatusListEntry;

/**
 * The register's URLs on example.com.
 */
final class FixedUrls implements CertificateUrls
{
    #[Override]
    public function course(string $slug): string
    {
        return 'https://example.com/certificates/courses/'.$slug;
    }

    #[Override]
    public function certificate(VerificationCode $code): string
    {
        return 'https://example.com/certificates/'.$code->value;
    }

    #[Override]
    public function statusEntry(string $list, int $index): StatusListEntry
    {
        return new StatusListEntry('https://example.com/open-badges/status/'.$list, $index);
    }
}

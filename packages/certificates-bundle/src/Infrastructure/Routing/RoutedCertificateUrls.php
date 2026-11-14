<?php

declare(strict_types=1);

namespace Phil\CertificatesBundle\Infrastructure\Routing;

use Override;
use Phil\CertificatesBundle\Application\Contract\CertificateUrls;
use Phil\CertificatesBundle\Domain\ValueObject\VerificationCode;
use Phil\OpenBadges\Status\StatusListEntry;
use Phil\OpenBadgesBundle\Infrastructure\Routing\StatusListUrls;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The URLs of the register's routes and of the issuer's status lists, absolute.
 */
final readonly class RoutedCertificateUrls implements CertificateUrls
{
    public const string COURSE_ROUTE = 'phil_certificates_course';

    public const string CERTIFICATE_ROUTE = 'phil_certificates_certificate';

    public function __construct(
        private UrlGeneratorInterface $urls,
        private StatusListUrls $statusLists,
    ) {
    }

    #[Override]
    public function course(string $slug): string
    {
        return $this->urls->generate(self::COURSE_ROUTE, ['slug' => $slug], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    #[Override]
    public function certificate(VerificationCode $code): string
    {
        return $this->urls->generate(self::CERTIFICATE_ROUTE, ['code' => $code->value], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    #[Override]
    public function statusEntry(string $list, int $index): StatusListEntry
    {
        return $this->statusLists->entry($list, $index);
    }
}

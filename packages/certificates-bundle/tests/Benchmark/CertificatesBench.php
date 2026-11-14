<?php

declare(strict_types=1);

namespace Phil\CertificatesBundle\Tests\Benchmark;

use DateTimeImmutable;
use Phil\CertificatesBundle\Application\CertificateStatusLists;
use Phil\CertificatesBundle\Application\StatusListLayout;
use Phil\CertificatesBundle\Domain\Model\Certificate;
use Phil\CertificatesBundle\Domain\ValueObject\VerificationCode;
use Phil\CertificatesBundle\Tests\Unit\Support\InMemoryCertificates;
use Phil\CertificatesBundle\Tests\Unit\Support\Kit;

/**
 * What the register pays on each visit and each publication of a status list: reading the code a visitor typed,
 * placing a status index and a list name in the layout, and the revoked indexes of a list of ten thousand certificates
 * of which one in ten is revoked.
 */
final readonly class CertificatesBench
{
    private StatusListLayout $layout;

    private CertificateStatusLists $lists;

    public function __construct()
    {
        $this->layout = new StatusListLayout('certificates', 131_072);
        $certificates = new InMemoryCertificates();
        $course = Kit::firstAid();
        $revokedAt = new DateTimeImmutable('2026-12-01 12:00 UTC');

        for ($index = 0; $index < 10_000; ++$index) {
            $certificate = Certificate::award(\sprintf('0190f1d2-3c4b-7a8e-9f00-%012d', $index), VerificationCode::generate(), $course, 'Ada Lovelace', new DateTimeImmutable('2026-11-08 10:00:00 UTC'), $index, 'header.payload.signature');

            if (0 === $index % 10) {
                $certificate->revoke('Fraud.', $revokedAt);
            }

            $certificates->save($certificate);
        }

        $this->lists = new CertificateStatusLists($certificates, $this->layout);
    }

    public function benchReadACodeAsTyped(): void
    {
        VerificationCode::fromString('7kq2-m9xa-o4tz');
    }

    public function benchPlaceAnIndexAndAList(): void
    {
        $this->layout->locate(262_145);
        $this->layout->range('certificates-2');
    }

    public function benchRevokedIndexesOfAList(): void
    {
        $this->lists->find('certificates-0');
    }
}

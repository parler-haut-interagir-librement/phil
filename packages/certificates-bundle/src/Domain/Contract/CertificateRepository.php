<?php

declare(strict_types=1);

namespace Phil\CertificatesBundle\Domain\Contract;

use DateTimeImmutable;
use Phil\CertificatesBundle\Domain\Model\Certificate;
use Phil\CertificatesBundle\Domain\Model\Course;

/**
 * Where the certificates are kept, with the index of each in the issuer's status lists.
 */
interface CertificateRepository
{
    public function save(Certificate $certificate): void;

    public function findByCode(string $code): ?Certificate;

    /**
     * The status index the next certificate takes: one past the highest given, 0 for the first. The storage keeps the
     * indexes unique, so that two awards at once cannot share one.
     */
    public function nextStatusIndex(): int;

    /**
     * The status indexes of the revoked certificates from `$from` included to `$to` excluded, in order.
     *
     * @return list<int>
     */
    public function revokedIndexes(int $from, int $to): array;

    /**
     * The listed certificates of a course, the latest awarded first.
     *
     * @return list<Certificate>
     */
    public function listed(Course $course, int $limit, int $offset = 0): array;

    public function countListed(Course $course): int;

    /**
     * The certificate awarded by the recertification of this one, when there is one.
     */
    public function findRenewalOf(Certificate $certificate): ?Certificate;

    /**
     * The certificates that end after `$after` and by `$until` included, neither revoked nor renewed, the first to end
     * first.
     *
     * @return list<Certificate>
     */
    public function expiring(DateTimeImmutable $after, DateTimeImmutable $until, int $limit, int $offset = 0): array;
}

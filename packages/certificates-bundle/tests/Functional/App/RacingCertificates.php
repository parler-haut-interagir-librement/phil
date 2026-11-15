<?php

declare(strict_types=1);

namespace Phil\CertificatesBundle\Tests\Functional\App;

use Closure;
use DateTimeImmutable;
use Override;
use Phil\CertificatesBundle\Application\Awarding;
use Phil\CertificatesBundle\Domain\Contract\CertificateRepository;
use Phil\CertificatesBundle\Domain\Model\Certificate;
use Phil\CertificatesBundle\Domain\Model\Course;
use Phil\CertificatesBundle\Domain\ValueObject\Attainment;

/**
 * The application's certificates, where another award slips in, as many times as `$races` says, between the moment an
 * index is given and the moment the certificate that took it is saved: the rival stores its certificate at that index
 * first. The rival awards a certificate of `first-aid` to another learner, or what `$award` makes it award.
 */
final class RacingCertificates implements CertificateRepository
{
    /** @var list<int> */
    public array $given = [];

    /** @var list<Certificate> */
    public array $rivals = [];

    /**
     * @param (Closure(Awarding): Certificate)|null $award
     */
    public function __construct(
        private readonly CertificateRepository $inner,
        private readonly Awarding $rival,
        private int $races,
        private readonly ?Closure $award = null,
    ) {
    }

    #[Override]
    public function nextStatusIndex(): int
    {
        $index = $this->inner->nextStatusIndex();
        $this->given[] = $index;

        if ($this->races > 0) {
            --$this->races;
            $this->rivals[] = $this->award instanceof Closure ? ($this->award)($this->rival) : $this->rival->award('first-aid', 'Grace Hopper', 'grace@example.org', new Attainment(90));
        }

        return $index;
    }

    #[Override]
    public function save(Certificate $certificate): void
    {
        $this->inner->save($certificate);
    }

    #[Override]
    public function findByCode(string $code): ?Certificate
    {
        return $this->inner->findByCode($code);
    }

    #[Override]
    public function revokedIndexes(int $from, int $to): array
    {
        return $this->inner->revokedIndexes($from, $to);
    }

    #[Override]
    public function listed(Course $course, int $limit, int $offset = 0): array
    {
        return $this->inner->listed($course, $limit, $offset);
    }

    #[Override]
    public function countListed(Course $course): int
    {
        return $this->inner->countListed($course);
    }

    #[Override]
    public function findRenewalOf(Certificate $certificate): ?Certificate
    {
        return $this->inner->findRenewalOf($certificate);
    }

    #[Override]
    public function findByCompletion(Course $course, string $completion): ?Certificate
    {
        return $this->inner->findByCompletion($course, $completion);
    }

    #[Override]
    public function expiring(DateTimeImmutable $after, DateTimeImmutable $until, int $limit, int $offset = 0): array
    {
        return $this->inner->expiring($after, $until, $limit, $offset);
    }
}

<?php

declare(strict_types=1);

namespace Phil\CertificatesBundle\Tests\Unit\Support;

use DateTimeImmutable;
use Override;
use Phil\CertificatesBundle\Application\Awarding;
use Phil\CertificatesBundle\Domain\Contract\CertificateRepository;
use Phil\CertificatesBundle\Domain\Exception\StatusIndexTaken;
use Phil\CertificatesBundle\Domain\Model\Certificate;
use Phil\CertificatesBundle\Domain\Model\Course;
use RuntimeException;
use Symfony\Component\Lock\LockFactory;

/**
 * Certificates in memory that note, when the next index is asked and when one is saved, whether the lock of the status
 * index was held at that moment; and that can refuse to save.
 */
final class LockWatchingCertificates implements CertificateRepository
{
    /** @var list<string> */
    public array $held = [];

    public bool $refuse = false;

    /** How many saves are refused because another certificate took the index in the meantime. */
    public int $taken = 0;

    /** Whether the certificates are forgotten when a save is refused because the index was taken. */
    public bool $forget = false;

    public function __construct(
        private readonly LockFactory $locks,
        public readonly InMemoryCertificates $inner = new InMemoryCertificates(),
    ) {
    }

    #[Override]
    public function nextStatusIndex(): int
    {
        $this->held[] = 'next:'.($this->isHeld() ? 'held' : 'free');

        return $this->inner->nextStatusIndex();
    }

    #[Override]
    public function save(Certificate $certificate): void
    {
        $this->held[] = 'save:'.($this->isHeld() ? 'held' : 'free');

        if ($this->refuse) {
            throw new RuntimeException('The storage refuses.');
        }

        if ($this->taken > 0) {
            --$this->taken;

            if ($this->forget) {
                $this->inner->certificates = [];
            }

            throw StatusIndexTaken::at($certificate->statusIndex);
        }

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

    public function isHeld(): bool
    {
        $lock = $this->locks->createLock(Awarding::LOCK);

        if ($lock->acquire()) {
            $lock->release();

            return false;
        }

        return true;
    }

    #[Override]
    public function findRenewalOf(Certificate $certificate): ?Certificate
    {
        return $this->inner->findRenewalOf($certificate);
    }

    #[Override]
    public function expiring(DateTimeImmutable $after, DateTimeImmutable $until, int $limit, int $offset = 0): array
    {
        return $this->inner->expiring($after, $until, $limit, $offset);
    }
}

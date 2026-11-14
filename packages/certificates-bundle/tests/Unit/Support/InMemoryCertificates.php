<?php

declare(strict_types=1);

namespace Phil\CertificatesBundle\Tests\Unit\Support;

use DateTimeImmutable;
use LogicException;
use Override;
use Phil\CertificatesBundle\Domain\Contract\CertificateRepository;
use Phil\CertificatesBundle\Domain\Model\Certificate;
use Phil\CertificatesBundle\Domain\Model\Course;

final class InMemoryCertificates implements CertificateRepository
{
    /** @var array<string, Certificate> */
    public array $certificates = [];

    public int $saved = 0;

    /** How many pages of expiring certificates were read. */
    public int $reads = 0;

    /** The most pages that may be read: a reader that never stops is refused instead of looping. */
    public ?int $readLimit = 100;

    public function __construct(
        public int $next = 0,
    ) {
    }

    #[Override]
    public function save(Certificate $certificate): void
    {
        $this->certificates[$certificate->code->value] = $certificate;
        $this->next = max($this->next, $certificate->statusIndex + 1);
        ++$this->saved;
    }

    #[Override]
    public function findByCode(string $code): ?Certificate
    {
        return $this->certificates[$code] ?? null;
    }

    #[Override]
    public function nextStatusIndex(): int
    {
        return $this->next;
    }

    #[Override]
    public function revokedIndexes(int $from, int $to): array
    {
        $indexes = [];

        foreach ($this->certificates as $certificate) {
            if ($certificate->revokedAt instanceof DateTimeImmutable && $certificate->statusIndex >= $from && $certificate->statusIndex < $to) {
                $indexes[] = $certificate->statusIndex;
            }
        }

        sort($indexes);

        return $indexes;
    }

    #[Override]
    public function listed(Course $course, int $limit, int $offset = 0): array
    {
        if ($offset < 0 || $limit < 1) {
            throw new LogicException('A page starts at 0 and holds at least one certificate.');
        }

        $listed = array_values(array_filter($this->certificates, static fn (Certificate $certificate): bool => $certificate->listed && $certificate->course === $course));

        return \array_slice($listed, $offset, $limit);
    }

    #[Override]
    public function countListed(Course $course): int
    {
        return \count($this->listed($course, \PHP_INT_MAX));
    }

    #[Override]
    public function findRenewalOf(Certificate $certificate): ?Certificate
    {
        foreach ($this->certificates as $renewal) {
            if ($renewal->renews === $certificate) {
                return $renewal;
            }
        }

        return null;
    }

    #[Override]
    public function expiring(DateTimeImmutable $after, DateTimeImmutable $until, int $limit, int $offset = 0): array
    {
        if ($offset < 0 || $limit < 1) {
            throw new LogicException('A page starts at 0 and holds at least one certificate.');
        }

        ++$this->reads;

        if (null !== $this->readLimit && $this->reads > $this->readLimit) {
            throw new LogicException('The reader asked for more pages than it should.');
        }

        $renewed = [];

        foreach ($this->certificates as $renewal) {
            if ($renewal->renews instanceof Certificate) {
                $renewed[spl_object_id($renewal->renews)] = true;
            }
        }

        $expiring = array_filter($this->certificates, static fn (Certificate $certificate): bool => !$certificate->revokedAt instanceof DateTimeImmutable
            && $certificate->validUntil instanceof DateTimeImmutable && $certificate->validUntil > $after && $certificate->validUntil <= $until
            && !isset($renewed[spl_object_id($certificate)]));
        usort($expiring, static fn (Certificate $a, Certificate $b): int => [$a->validUntil?->getTimestamp(), $a->id] <=> [$b->validUntil?->getTimestamp(), $b->id]);

        return \array_slice($expiring, $offset, $limit);
    }
}

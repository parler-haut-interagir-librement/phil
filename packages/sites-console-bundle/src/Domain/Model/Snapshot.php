<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Domain\Model;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Phil\SitesConsoleBundle\Domain\Status\HealthState;
use Phil\SitesConsoleBundle\Domain\ValueObject\CheckState;
use Phil\SitesConsoleBundle\Domain\ValueObject\HealthSummary;
use Phil\SitesConsoleBundle\Domain\ValueObject\StatusDocument;
use Symfony\Component\Uid\Ulid;

/**
 * What the console read of a site at one moment: its versions and its health, or the error that kept it from being
 * read.
 */
#[ORM\Entity]
#[ORM\Table(name: self::TABLE)]
#[ORM\Index(name: 'phil_sites_console_snapshot_site', columns: ['site_id', 'taken_at'])]
final class Snapshot
{
    public const string TABLE = 'phil_sites_console_snapshot';

    public const int ERROR_MAX_LENGTH = 500;

    /**
     * @param array<string, string>                                      $packages
     * @param list<array{name: string, status: string, summary: string}> $checks
     */
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'id', length: 26, options: ['fixed' => true])]
        private readonly string $id,
        #[ORM\Column(name: 'site_id', length: 64)]
        private readonly string $siteId,
        #[ORM\Column(name: 'taken_at')]
        private readonly DateTimeImmutable $takenAt,
        #[ORM\Column(name: 'reachable')]
        private readonly bool $reachable,
        #[ORM\Column(name: 'health', length: 10, enumType: HealthState::class)]
        private readonly HealthState $health,
        #[ORM\Column(name: 'checks', type: 'json')]
        private readonly array $checks,
        #[ORM\Column(name: 'packages', type: 'json')]
        private readonly array $packages,
        #[ORM\Column(name: 'php_version', length: 64, nullable: true)]
        private readonly ?string $php,
        #[ORM\Column(name: 'symfony_version', length: 64, nullable: true)]
        private readonly ?string $symfony,
        #[ORM\Column(name: 'error', length: self::ERROR_MAX_LENGTH, nullable: true)]
        private readonly ?string $error,
    ) {
    }

    public static function reached(string $siteId, StatusDocument $status, DateTimeImmutable $now): self
    {
        return new self(new Ulid()->toBase32(), $siteId, $now, true, $status->health->state, $status->health->toArray()['checks'], $status->packages, $status->php, $status->symfony, null);
    }

    public static function unreachable(string $siteId, string $error, DateTimeImmutable $now): self
    {
        return new self(new Ulid()->toBase32(), $siteId, $now, false, HealthState::Unknown, [], [], null, null, mb_substr($error, 0, self::ERROR_MAX_LENGTH));
    }

    public function id(): string
    {
        return $this->id;
    }

    public function siteId(): string
    {
        return $this->siteId;
    }

    public function takenAt(): DateTimeImmutable
    {
        return $this->takenAt;
    }

    public function isReachable(): bool
    {
        return $this->reachable;
    }

    public function health(): HealthSummary
    {
        return new HealthSummary($this->health, array_map(CheckState::fromArray(...), $this->checks));
    }

    /**
     * @return array<string, string>
     */
    public function packages(): array
    {
        // A JSON object may come back from the database in another order.
        $packages = $this->packages;
        ksort($packages, \SORT_STRING);

        return $packages;
    }

    public function version(string $package): ?string
    {
        return $this->packages[$package] ?? null;
    }

    public function php(): ?string
    {
        return $this->php;
    }

    public function symfony(): ?string
    {
        return $this->symfony;
    }

    public function error(): ?string
    {
        return $this->error;
    }
}

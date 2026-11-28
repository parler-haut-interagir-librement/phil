<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Domain\Model;

use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;
use Phil\SitesConsoleBundle\Domain\Exception\InvalidValue;
use Phil\SitesConsoleBundle\Domain\Status\DeliveryOutcome;
use Phil\SitesConsoleBundle\Domain\ValueObject\Delivery;
use Phil\SitesConsoleBundle\Domain\ValueObject\Outcome;
use Phil\SitesConsoleBundle\Domain\ValueObject\Settings;
use Symfony\Component\Uid\Ulid;

/**
 * Common settings pushed to several sites, and where each delivery stands. Its identifier is the idempotency key the
 * agents see: delivering it again never applies it twice.
 *
 * @phpstan-type DeliveryRow array{site: string, outcome: string, message: string, at: ?string}
 */
#[ORM\Entity]
#[ORM\Table(name: self::TABLE)]
final class ConfigurationPush
{
    public const string TABLE = 'phil_sites_console_push';

    public const int MAX_SITES = 500;

    /**
     * @param array<string, scalar|null> $settings
     * @param list<DeliveryRow>          $deliveries in the order the sites were given; a list, since a JSON object
     *                                               may come back from the database in another order
     */
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'id', length: 26, options: ['fixed' => true])]
        private readonly string $id,
        #[ORM\Column(name: 'settings', type: 'json')]
        private readonly array $settings,
        #[ORM\Column(name: 'deliveries', type: 'json')]
        private array $deliveries,
        #[ORM\Column(name: 'created_at')]
        private readonly DateTimeImmutable $createdAt,
        #[ORM\Column(name: 'created_by', length: 180, nullable: true)]
        private readonly ?string $createdBy,
    ) {
    }

    /**
     * @param list<string> $siteIds
     */
    public static function create(Settings $settings, array $siteIds, ?string $actor, DateTimeImmutable $now): self
    {
        $unique = array_values(array_unique($siteIds));

        if ([] === $unique || \count($unique) > self::MAX_SITES) {
            throw InvalidValue::because(\sprintf('A push goes to 1 to %d sites.', self::MAX_SITES));
        }

        $deliveries = [];

        foreach ($unique as $siteId) {
            $deliveries[] = ['site' => $siteId, 'outcome' => DeliveryOutcome::Pending->value, 'message' => '', 'at' => null];
        }

        return new self(new Ulid()->toBase32(), $settings->values, $deliveries, $now, $actor);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function settings(): Settings
    {
        return Settings::of($this->settings);
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function createdBy(): ?string
    {
        return $this->createdBy;
    }

    /**
     * The sites the push has not reached yet: pending or failed.
     *
     * @return list<string>
     */
    public function undeliveredSites(): array
    {
        $sites = [];

        foreach ($this->deliveries() as $delivery) {
            if (!$delivery->outcome->isDelivered()) {
                $sites[] = $delivery->siteId;
            }
        }

        return $sites;
    }

    public function record(string $siteId, Outcome $outcome, DateTimeImmutable $now): DeliveryOutcome
    {
        $position = array_find_key($this->deliveries, static fn (array $row): bool => $row['site'] === $siteId);

        if (null === $position) {
            throw InvalidValue::because(\sprintf('The push %s does not go to the site "%s".', $this->id, $siteId));
        }

        $result = match (true) {
            !$outcome->succeeded => DeliveryOutcome::Failed,
            $outcome->replayed => DeliveryOutcome::Replayed,
            default => DeliveryOutcome::Applied,
        };
        $this->deliveries[$position] = ['site' => $siteId, 'outcome' => $result->value, 'message' => $outcome->message, 'at' => $now->format(DateTimeInterface::ATOM)];

        return $result;
    }

    /**
     * @return list<Delivery>
     */
    public function deliveries(): array
    {
        $deliveries = [];

        foreach ($this->deliveries as $row) {
            $at = null === $row['at'] ? null : DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $row['at']);
            $deliveries[] = new Delivery($row['site'], DeliveryOutcome::from($row['outcome']), $row['message'], false === $at ? null : $at);
        }

        return $deliveries;
    }
}

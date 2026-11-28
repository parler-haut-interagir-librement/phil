<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Domain\Model;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Phil\SitesConsoleBundle\Domain\Exception\InvalidValue;
use Phil\SitesConsoleBundle\Domain\Signature\VerifyingKey;
use Phil\SitesConsoleBundle\Domain\Status\KeyOperation;
use Phil\SitesConsoleBundle\Domain\Status\RotationPhase;
use Phil\SitesConsoleBundle\Domain\Status\RotationStep;
use Phil\SitesConsoleBundle\Domain\ValueObject\Outcome;
use Symfony\Component\Uid\Ulid;

/**
 * A rotation of the console key: the previous and the next public key, the phase, and for each site whether the next
 * key is staged there and the previous one retired. Only public keys are kept; the private keys stay in the
 * configuration of the console.
 *
 * @phpstan-type ProgressRow array{site: string, stage: string, retire: string, message: string}
 */
#[ORM\Entity]
#[ORM\Table(name: self::TABLE)]
#[ORM\Index(name: 'phil_sites_console_rotation_next', columns: ['next_key'])]
final class KeyRotation
{
    public const string TABLE = 'phil_sites_console_key_rotation';

    public const int MAX_SITES = 1000;

    /**
     * @param list<ProgressRow> $progress in the order the sites were given; a list, since a JSON object may come back
     *                                    from the database in another order
     */
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'id', length: 26, options: ['fixed' => true])]
        private readonly string $id,
        #[ORM\Column(name: 'previous_key', length: 64)]
        private readonly string $previousKey,
        #[ORM\Column(name: 'next_key', length: 64)]
        private readonly string $nextKey,
        #[ORM\Column(name: 'phase', length: 16, enumType: RotationPhase::class)]
        private RotationPhase $phase,
        #[ORM\Column(name: 'progress', type: 'json')]
        private array $progress,
        #[ORM\Column(name: 'started_at')]
        private readonly DateTimeImmutable $startedAt,
        #[ORM\Column(name: 'started_by', length: 180, nullable: true)]
        private readonly ?string $startedBy,
        #[ORM\Column(name: 'updated_at')]
        private DateTimeImmutable $updatedAt,
    ) {
    }

    /**
     * @param list<string> $siteIds
     */
    public static function start(VerifyingKey $previous, VerifyingKey $next, array $siteIds, ?string $actor, DateTimeImmutable $now): self
    {
        if ($previous->equals($next)) {
            throw InvalidValue::because('The next key of the console is its current key.');
        }

        $unique = array_values(array_unique($siteIds));

        if (\count($unique) > self::MAX_SITES) {
            throw InvalidValue::because(\sprintf('A rotation reaches at most %d sites.', self::MAX_SITES));
        }

        $progress = array_map(static fn (string $site): array => ['site' => $site, 'stage' => RotationStep::Pending->value, 'retire' => RotationStep::Pending->value, 'message' => ''], $unique);

        return new self(new Ulid()->toBase32(), $previous->toBase64(), $next->toBase64(), RotationPhase::Staging, $progress, $now, null === $actor ? null : mb_substr($actor, 0, 180), $now);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function previousKey(): VerifyingKey
    {
        return VerifyingKey::fromBase64($this->previousKey);
    }

    public function nextKey(): VerifyingKey
    {
        return VerifyingKey::fromBase64($this->nextKey);
    }

    public function phase(): RotationPhase
    {
        return $this->phase;
    }

    public function startedAt(): DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function startedBy(): ?string
    {
        return $this->startedBy;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * The idempotency key of one step on one site: the same on every attempt, so that a step delivered again is
     * applied once.
     */
    public function requestId(KeyOperation $operation, string $siteId): string
    {
        return $this->id.':'.$operation->value.':'.$siteId;
    }

    /**
     * The sites whose step of the given operation is not settled yet, in order.
     *
     * @return list<string>
     */
    public function unsettled(KeyOperation $operation): array
    {
        $sites = [];

        foreach ($this->progress as $row) {
            if (!RotationStep::from($row[$this->column($operation)])->isSettled()) {
                $sites[] = $row['site'];
            }
        }

        return $sites;
    }

    /**
     * Records what a site answered to a step.
     */
    public function record(KeyOperation $operation, string $siteId, Outcome $outcome, DateTimeImmutable $now): RotationStep
    {
        $step = $outcome->succeeded ? RotationStep::Done : RotationStep::Failed;
        $this->write($siteId, KeyOperation::Stage === $operation ? ['stage' => $step->value, 'message' => $outcome->message] : ['retire' => $step->value, 'message' => $outcome->message], $now);

        return $step;
    }

    /**
     * Leaves out a site that left the console during the rotation.
     */
    public function skip(string $siteId, DateTimeImmutable $now): void
    {
        $this->write($siteId, ['stage' => RotationStep::Skipped->value, 'retire' => RotationStep::Skipped->value, 'message' => 'The site left the console.'], $now);
    }

    /**
     * The console signs with the next key from now on; every site holds it.
     *
     * @throws InvalidValue when the rotation is not staging, or a site does not hold the next key yet
     */
    public function switchSigning(DateTimeImmutable $now): void
    {
        $this->advance(RotationPhase::Staging, KeyOperation::Stage, RotationPhase::Retiring, $now);
    }

    /**
     * The rotation is over; no site holds the previous key any more.
     *
     * @throws InvalidValue when the rotation is not retiring, or a site still holds the previous key
     */
    public function finish(DateTimeImmutable $now): void
    {
        $this->advance(RotationPhase::Retiring, KeyOperation::Retire, RotationPhase::Finished, $now);
    }

    /**
     * @return list<array{site: string, stage: RotationStep, retire: RotationStep, message: string}>
     */
    public function progress(): array
    {
        return array_map(static fn (array $row): array => ['site' => $row['site'], 'stage' => RotationStep::from($row['stage']), 'retire' => RotationStep::from($row['retire']), 'message' => $row['message']], $this->progress);
    }

    private function advance(RotationPhase $from, KeyOperation $operation, RotationPhase $to, DateTimeImmutable $now): void
    {
        if ($from !== $this->phase || [] !== $this->unsettled($operation)) {
            throw InvalidValue::because(\sprintf('The rotation %s cannot be %s now.', $this->id, RotationPhase::Retiring === $to ? 'switched to the next key' : 'finished'));
        }

        $this->phase = $to;
        $this->updatedAt = $now;
    }

    /**
     * @param array{stage?: string, retire?: string, message: string} $changes
     */
    private function write(string $siteId, array $changes, DateTimeImmutable $now): void
    {
        $position = array_find_key($this->progress, static fn (array $row): bool => $row['site'] === $siteId);

        if (null === $position) {
            throw InvalidValue::because(\sprintf('The rotation %s does not reach the site "%s".', $this->id, $siteId));
        }

        $this->progress[$position] = array_replace($this->progress[$position], $changes);
        $this->updatedAt = $now;
    }

    private function column(KeyOperation $operation): string
    {
        return KeyOperation::Stage === $operation ? 'stage' : 'retire';
    }
}

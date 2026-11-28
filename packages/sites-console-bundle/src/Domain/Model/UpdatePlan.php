<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Domain\Model;

use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;
use Phil\SitesConsoleBundle\Domain\Exception\InvalidValue;
use Phil\SitesConsoleBundle\Domain\Status\PlanState;
use Phil\SitesConsoleBundle\Domain\Status\StepOutcome;
use Phil\SitesConsoleBundle\Domain\ValueObject\Outcome;
use Phil\SitesConsoleBundle\Domain\ValueObject\UpdateStep;
use Phil\SitesConsoleBundle\Domain\Version\Packages;
use Symfony\Component\Uid\Ulid;

/**
 * One package brought to one version on several sites, in batches, one site after the other: the first failure stops
 * the plan, and the sites not reached yet are skipped. Each step keeps its outcome, which makes the report per site.
 *
 * @phpstan-type StepRow array{site: string, batch: int, outcome: string, message: string, finished_at: ?string}
 */
#[ORM\Entity]
#[ORM\Table(name: self::TABLE)]
final class UpdatePlan
{
    public const string TABLE = 'phil_sites_console_update_plan';

    public const int MAX_SITES = 500;

    /**
     * @param list<StepRow> $steps
     */
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'id', length: 26, options: ['fixed' => true])]
        private readonly string $id,
        #[ORM\Column(name: 'package', length: 214)]
        private readonly string $package,
        #[ORM\Column(name: 'version', length: Packages::VERSION_MAX_LENGTH)]
        private readonly string $version,
        #[ORM\Column(name: 'batch_size')]
        private readonly int $batchSize,
        #[ORM\Column(name: 'state', length: 10, enumType: PlanState::class)]
        private PlanState $state,
        #[ORM\Column(name: 'steps', type: 'json')]
        private array $steps,
        #[ORM\Column(name: 'created_at')]
        private readonly DateTimeImmutable $createdAt,
        #[ORM\Column(name: 'created_by', length: 180, nullable: true)]
        private readonly ?string $createdBy,
    ) {
    }

    /**
     * @param list<string> $siteIds in the order they are updated
     */
    public static function plan(string $package, string $version, array $siteIds, int $batchSize, ?string $actor, DateTimeImmutable $now): self
    {
        if (!Packages::isName($package) || !Packages::isVersion($version)) {
            throw InvalidValue::because('An update names a package and a version.');
        }

        $unique = array_values(array_unique($siteIds));

        if ([] === $unique || \count($unique) > self::MAX_SITES) {
            throw InvalidValue::because(\sprintf('An update plan covers 1 to %d sites.', self::MAX_SITES));
        }

        if ($batchSize < 1) {
            throw InvalidValue::because('A batch holds at least one site.');
        }

        $steps = [];

        foreach ($unique as $position => $siteId) {
            $steps[] = ['site' => $siteId, 'batch' => intdiv($position, $batchSize) + 1, 'outcome' => StepOutcome::Pending->value, 'message' => '', 'finished_at' => null];
        }

        return new self(new Ulid()->toBase32(), $package, $version, $batchSize, PlanState::Planned, $steps, $now, $actor);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function package(): string
    {
        return $this->package;
    }

    public function version(): string
    {
        return $this->version;
    }

    public function batchSize(): int
    {
        return $this->batchSize;
    }

    public function state(): PlanState
    {
        return $this->state;
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
     * The position of the next site to update, or null when the plan is finished.
     */
    public function next(): ?int
    {
        if ($this->state->isFinished()) {
            return null;
        }

        foreach ($this->steps as $position => $step) {
            if (StepOutcome::Pending->value === $step['outcome']) {
                return $position;
            }
        }

        return null;
    }

    /**
     * The idempotency key of a step: the plan and the site.
     */
    public function stepKey(int $position): string
    {
        return $this->id.':'.$this->step($position)['site'];
    }

    public function siteAt(int $position): string
    {
        return $this->step($position)['site'];
    }

    public function batchAt(int $position): int
    {
        return $this->step($position)['batch'];
    }

    /**
     * Records the outcome of the pending step at that position: a failure stops the plan and skips every site not
     * reached; the last success completes it.
     */
    public function record(int $position, Outcome $outcome, DateTimeImmutable $now): StepOutcome
    {
        if (StepOutcome::Pending->value !== $this->step($position)['outcome'] || $this->state->isFinished()) {
            throw InvalidValue::because(\sprintf('The step %d of the plan %s is not pending.', $position, $this->id));
        }

        $result = $outcome->succeeded ? StepOutcome::Succeeded : StepOutcome::Failed;
        $this->steps[$position] = ['outcome' => $result->value, 'message' => $outcome->message, 'finished_at' => $now->format(DateTimeInterface::ATOM)] + $this->steps[$position];
        $this->state = PlanState::Running;

        if (StepOutcome::Failed === $result) {
            foreach ($this->steps as $index => $step) {
                if (StepOutcome::Pending->value === $step['outcome']) {
                    $this->steps[$index]['outcome'] = StepOutcome::Skipped->value;
                }
            }

            $this->state = PlanState::Stopped;

            return $result;
        }

        if (null === $this->next()) {
            $this->state = PlanState::Completed;
        }

        return $result;
    }

    /**
     * @return list<UpdateStep>
     */
    public function steps(): array
    {
        return array_map(static function (array $step): UpdateStep {
            $finishedAt = null === $step['finished_at'] ? null : DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $step['finished_at']);

            return new UpdateStep($step['site'], $step['batch'], StepOutcome::from($step['outcome']), $step['message'], false === $finishedAt ? null : $finishedAt);
        }, $this->steps);
    }

    /**
     * @return StepRow
     */
    private function step(int $position): array
    {
        return $this->steps[$position] ?? throw InvalidValue::because(\sprintf('The plan %s has no step %d.', $this->id, $position));
    }
}

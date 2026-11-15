<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Application;

use Phil\ModerationBundle\Application\Contract\CurrentModerator;
use Phil\ModerationBundle\Domain\Contract\DecisionRepository;
use Phil\ModerationBundle\Domain\Contract\ReportRepository;
use Phil\ModerationBundle\Domain\ValueObject\QueueItem;
use Phil\ModerationBundle\Domain\ValueObject\TargetReference;

/**
 * The single queue: every target with open reports, of every registered type, that the current moderator's role
 * covers, the earliest report first.
 */
final readonly class ModerationQueue
{
    public function __construct(
        private ReportRepository $reports,
        private DecisionRepository $decisions,
        private ModerationTargets $targets,
        private CurrentModerator $currentModerator,
    ) {
    }

    /**
     * @param positive-int     $limit
     * @param non-negative-int $offset
     *
     * @return list<QueueItem>
     *
     * @phpstan-impure
     */
    public function items(int $limit, int $offset = 0): array
    {
        $types = $this->types();

        return null === $types ? [] : $this->reports->queue($limit, $offset, $types);
    }

    /**
     * @phpstan-impure
     */
    public function count(): int
    {
        $types = $this->types();

        return null === $types ? 0 : $this->reports->countOpenTargets($types);
    }

    /**
     * @phpstan-impure
     */
    public function entry(TargetReference $target): QueueEntry
    {
        return new QueueEntry(
            $target,
            $this->targets->has($target->type) ? $this->targets->get($target->type)->describe($target->id) : null,
            $this->reports->openOn($target),
            $this->decisions->on($target),
        );
    }

    /**
     * @phpstan-impure
     */
    public function describe(TargetReference $target): ?TargetView
    {
        return $this->targets->has($target->type) ? $this->targets->get($target->type)->describe($target->id) : null;
    }

    /**
     * The types the current moderator reviews — an empty list for every type —, or `null` when they are no moderator.
     *
     * @return list<string>|null
     */
    private function types(): ?array
    {
        $moderator = $this->currentModerator->moderator();

        return $moderator instanceof Moderator ? Roles::typesOf([$moderator->role]) : null;
    }
}

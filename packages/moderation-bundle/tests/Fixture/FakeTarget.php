<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Tests\Fixture;

use Override;
use Phil\ModerationBundle\Application\Contract\ModerationTarget;
use Phil\ModerationBundle\Application\TargetView;
use Phil\ModerationBundle\Domain\Status\DecisionAction;

/**
 * A package's content in an array: what the queue shows of each, and the actions applied to them, in order.
 */
final class FakeTarget implements ModerationTarget
{
    /** @var list<array{string, DecisionAction}> */
    public array $applied = [];

    /**
     * @param array<array-key, TargetView> $contents
     */
    public function __construct(
        private readonly string $type = 'forum.post',
        public array $contents = [],
    ) {
    }

    #[Override]
    public function type(): string
    {
        return $this->type;
    }

    #[Override]
    public function describe(string $id): ?TargetView
    {
        return $this->contents[$id] ?? null;
    }

    #[Override]
    public function apply(string $id, DecisionAction $action): void
    {
        $this->applied[] = [$id, $action];
    }
}

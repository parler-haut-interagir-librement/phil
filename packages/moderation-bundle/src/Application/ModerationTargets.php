<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Application;

use Phil\ModerationBundle\Application\Contract\ModerationTarget;
use Phil\ModerationBundle\Application\Exception\UnknownTarget;

/**
 * The targets the packages registered, by type.
 */
final readonly class ModerationTargets
{
    /** @var array<string, ModerationTarget> */
    private array $targets;

    /**
     * @param iterable<ModerationTarget> $targets
     */
    public function __construct(iterable $targets)
    {
        $byType = [];

        foreach ($targets as $target) {
            $byType[$target->type()] = $target;
        }

        $this->targets = $byType;
    }

    public function get(string $type): ModerationTarget
    {
        return $this->targets[$type] ?? throw UnknownTarget::ofType($type);
    }

    public function has(string $type): bool
    {
        return isset($this->targets[$type]);
    }

    /**
     * @return list<string>
     */
    public function types(): array
    {
        return array_keys($this->targets);
    }
}

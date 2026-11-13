<?php

declare(strict_types=1);

namespace Phil\StatusPageBundle\Infrastructure\Source;

use Override;
use Phil\StatusPageBundle\Application\Contract\ComponentStatuses;
use Phil\StatusPageBundle\Domain\Status\ComponentStatus;

/**
 * The state of each component as the application declares it in its configuration, the `status` of the component.
 */
final readonly class DeclaredStatuses implements ComponentStatuses
{
    /**
     * @param array<string, ComponentStatus> $statuses
     */
    public function __construct(
        private array $statuses,
    ) {
    }

    /**
     * @param array<string, string> $statuses the value of a ComponentStatus by component id
     */
    public static function fromValues(array $statuses): self
    {
        return new self(array_map(ComponentStatus::from(...), $statuses));
    }

    #[Override]
    public function statuses(array $components): array
    {
        return $this->statuses;
    }
}

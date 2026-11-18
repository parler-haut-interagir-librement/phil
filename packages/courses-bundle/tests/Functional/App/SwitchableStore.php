<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Tests\Functional\App;

use Override;
use Phil\CoursesBundle\Application\Contract\LearningRecordStore;
use Phil\CoursesBundle\Domain\Exception\RecordStoreUnavailable;

/**
 * The store inside the bundle, which a test makes refuse the statements, as a remote store out of reach would.
 */
final class SwitchableStore implements LearningRecordStore
{
    public bool $refuse = false;

    public function __construct(
        private readonly LearningRecordStore $inner,
    ) {
    }

    #[Override]
    public function store(string $statementId, string $statement, string $registration): void
    {
        if ($this->refuse) {
            throw RecordStoreUnavailable::status(503);
        }

        $this->inner->store($statementId, $statement, $registration);
    }

    #[Override]
    public function state(string $activityId, string $registration, string $stateId): ?string
    {
        return $this->inner->state($activityId, $registration, $stateId);
    }

    #[Override]
    public function saveState(string $activityId, string $registration, string $stateId, string $document): void
    {
        $this->inner->saveState($activityId, $registration, $stateId, $document);
    }

    #[Override]
    public function deleteState(string $activityId, string $registration, string $stateId): void
    {
        $this->inner->deleteState($activityId, $registration, $stateId);
    }
}

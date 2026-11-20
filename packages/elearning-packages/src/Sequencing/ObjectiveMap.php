<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Sequencing;

/**
 * A link between an objective of an activity and a shared global objective, `<imsss:mapInfo>`: whether the activity
 * reads the satisfaction and the measure of the global objective, and whether it writes its own to it.
 */
final readonly class ObjectiveMap
{
    public function __construct(
        public string $targetObjectiveId,
        public bool $readSatisfiedStatus = true,
        public bool $readNormalizedMeasure = true,
        public bool $writeSatisfiedStatus = false,
        public bool $writeNormalizedMeasure = false,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Application\Import;

use Phil\CoursesBundle\Domain\Model\Activity;
use Phil\CoursesBundle\Domain\Progress\CompletionRequirement;

/**
 * An activity the import will create: its title, type, settings, requirement, its identifier in the package, whether
 * the course needs it, and the block of the plan that holds it, by its index in the plan's blocks.
 *
 * @phpstan-import-type Settings from Activity
 */
final readonly class PlannedActivity
{
    /**
     * @param Settings         $settings
     * @param int<0, max>|null $block
     */
    public function __construct(
        public string $title,
        public string $type,
        public array $settings,
        public CompletionRequirement $requirement,
        public string $source,
        public bool $required = true,
        public ?int $block = null,
    ) {
    }
}

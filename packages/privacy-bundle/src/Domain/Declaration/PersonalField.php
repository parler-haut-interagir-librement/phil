<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Domain\Declaration;

use Phil\PrivacyBundle\Domain\Retention\RetentionSchedule;
use Phil\PrivacyBundle\Domain\Subject\DataCategory;

/**
 * One property declared personal by `#[PersonalData]`, as read from a class.
 */
final readonly class PersonalField
{
    /**
     * @param class-string $class the class that declares the property
     */
    public function __construct(
        public string $class,
        public string $property,
        public DataCategory $category,
        public ?string $retention,
    ) {
    }

    /**
     * Whether the field cites a retention rule at all, whether or not that rule exists.
     */
    public function isCoveredByRetention(): bool
    {
        return null !== $this->retention;
    }

    /**
     * Whether the rule the field cites is one the schedule defines.
     */
    public function isCoveredBy(RetentionSchedule $schedule): bool
    {
        return null !== $this->retention && $schedule->has($this->retention);
    }
}

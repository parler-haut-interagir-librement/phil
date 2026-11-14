<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Application\Register;

use Phil\PrivacyBundle\Domain\Declaration\PersonalField;
use Phil\PrivacyBundle\Domain\Retention\RetentionRule;

/**
 * One line of the register of processing: a personal field, its category, and the retention rule it names with what
 * the schedule says of it — `null` when it names none, or one the schedule does not hold.
 */
final readonly class RegisterEntry
{
    public function __construct(
        public PersonalField $field,
        public ?RetentionRule $rule,
    ) {
    }

    /**
     * @return array{category: string, class: string, property: string, retention: ?string, store: ?string, period: ?string, mechanism: ?string, erasure: ?string}
     */
    public function toArray(): array
    {
        return [
            'category' => $this->field->category->value,
            'class' => $this->field->class,
            'property' => $this->field->property,
            'retention' => $this->field->retention,
            'store' => $this->rule?->store,
            'period' => $this->rule instanceof RetentionRule ? $this->rule->period->duration ?? $this->rule->period->kind->value : (null),
            'mechanism' => $this->rule?->mechanism->value,
            'erasure' => $this->rule?->erasure->value,
        ];
    }
}

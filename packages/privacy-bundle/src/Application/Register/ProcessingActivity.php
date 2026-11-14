<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Application\Register;

use Phil\PrivacyBundle\Domain\Declaration\PersonalField;
use Phil\PrivacyBundle\Domain\Retention\Processor;
use Phil\PrivacyBundle\Domain\Retention\RetentionRule;
use Phil\PrivacyBundle\Domain\Subject\DataCategory;

/**
 * One activity of the register of processing: a store of the retention schedule, why it holds personal information,
 * who receives it, which service providers handle it, where it is kept, for how long, and the `#[PersonalData]` fields
 * that name its rule.
 */
final readonly class ProcessingActivity
{
    /**
     * @param list<PersonalField> $fields the fields whose declaration names this rule, by class then property
     */
    public function __construct(
        public RetentionRule $rule,
        public array $fields,
    ) {
    }

    public function isComplete(): bool
    {
        return $this->rule->processing->isComplete();
    }

    /**
     * @return array{rule: string, store: string, categories: list<string>, purpose: ?string, recipients: list<string>, processors: list<array{name: string, location: string}>, location: ?string, period: string, fields: list<string>}
     */
    public function toArray(): array
    {
        $processing = $this->rule->processing;

        return [
            'rule' => $this->rule->id,
            'store' => $this->rule->store,
            'categories' => array_map(static fn (DataCategory $category): string => $category->value, $this->rule->categories),
            'purpose' => $processing->purpose,
            'recipients' => $processing->recipients,
            'processors' => array_map(static fn (Processor $processor): array => ['name' => $processor->name, 'location' => $processor->location], $processing->processors),
            'location' => $processing->location,
            'period' => $this->rule->period->duration ?? $this->rule->period->kind->value,
            'fields' => array_map(static fn (PersonalField $field): string => $field->class.'::$'.$field->property, $this->fields),
        ];
    }
}

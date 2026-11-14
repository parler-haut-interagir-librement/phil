<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Infrastructure\Doctrine;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use Phil\PrivacyBundle\Domain\Anonymization\AnonymizationPlan;
use Phil\PrivacyBundle\Domain\Anonymization\AnonymizerOptions;
use Phil\PrivacyBundle\Domain\Anonymization\ColumnRule;
use Phil\PrivacyBundle\Domain\Declaration\DeclaredAnonymizations;
use Phil\PrivacyBundle\Domain\Declaration\DeclaredPersonalFields;
use Phil\PrivacyBundle\Domain\Declaration\PersonalField;

/**
 * Draws the anonymization plan from the Doctrine ORM mapping and the configuration.
 *
 * For each entity, a mapped property with `#[Anonymize]` gives a rule on its column. A `#[PersonalData]` property
 * without one takes the anonymizer the configuration gives its category (`categories: { email: email }`), or is
 * reported as uncovered. The configured `tables` come last and replace any rule on the same column; they also reach
 * tables no entity maps.
 *
 * Entities are read in the order of their class names, so the plan is the same from one run to the next. A field
 * inherited from a parent entity is read on the parent, whose table holds it in single table and joined
 * inheritance alike. Properties of embeddables are not read: name their columns under `tables`.
 */
final readonly class AnonymizationPlanReader
{
    /**
     * @param array<string, string>                                                                   $categories category to anonymizer type
     * @param array<string, array<string, array{type: string, options?: array<string, scalar|null>}>> $tables     table to column to rule
     */
    public function __construct(
        private ?ManagerRegistry $doctrine = null,
        private array $categories = [],
        private array $tables = [],
    ) {
    }

    public function plan(): AnonymizationPlan
    {
        $rules = [];
        $uncovered = [];

        foreach ($this->doctrine?->getManagers() ?? [] as $manager) {
            $entities = array_filter($manager->getMetadataFactory()->getAllMetadata(), static fn (object $metadata): bool => $metadata instanceof ClassMetadata && !$metadata->isMappedSuperclass && !$metadata->isEmbeddedClass);
            usort($entities, static fn (ClassMetadata $a, ClassMetadata $b): int => $a->getName() <=> $b->getName());

            foreach ($entities as $metadata) {
                array_push($rules, ...$this->entity($metadata, $uncovered));
            }
        }

        foreach ($this->tables as $table => $columns) {
            foreach ($columns as $column => $rule) {
                $rules[] = new ColumnRule($table, $column, $rule['type'], new AnonymizerOptions($rule['options'] ?? []), 'configuration');
            }
        }

        return new AnonymizationPlan($rules, array_merge(...array_values(array_map(array_values(...), $uncovered))));
    }

    /**
     * @param ClassMetadata<object>                       $metadata
     * @param array<string, array<string, PersonalField>> $uncovered
     *
     * @return list<ColumnRule>
     */
    private function entity(ClassMetadata $metadata, array &$uncovered): array
    {
        $class = $metadata->getName();
        $declared = DeclaredAnonymizations::of($class);
        $rules = [];

        foreach ($declared as $property => $field) {
            if ($this->owns($metadata, $property)) {
                $rules[] = new ColumnRule($metadata->getTableName(), $metadata->getColumnName($property), $field->anonymize->type, $field->anonymize->options, $field->class.'::$'.$property);
            }
        }

        foreach (DeclaredPersonalFields::of($class) as $field) {
            if (isset($declared[$field->property]) || !$this->owns($metadata, $field->property)) {
                continue;
            }

            $type = $this->categories[$field->category->value] ?? null;

            if (null === $type) {
                $uncovered[$field->class][$field->property] = $field;
            } else {
                $rules[] = new ColumnRule($metadata->getTableName(), $metadata->getColumnName($field->property), $type, new AnonymizerOptions(), 'category '.$field->category->value);
            }
        }

        return $rules;
    }

    /**
     * Whether the property is a column of this entity's own table rather than of a parent entity's.
     *
     * @param ClassMetadata<object> $metadata
     */
    private function owns(ClassMetadata $metadata, string $property): bool
    {
        return $metadata->hasField($property) && null === $metadata->getFieldMapping($property)->inherited;
    }
}

<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours;

use BackedEnum;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Phil\DoctrineBehaviours\Exception\InvalidScope;
use UnitEnum;

/**
 * The scope of a record as a condition on its table: the values its fields hold, a column or a to-one relation held
 * by one column each, null compared as `IS NULL`. The key, the values by field in JSON, tells scopes apart within a
 * flush. A relation to a record not stored yet reaches no row of the table: nothing stored can share it.
 *
 * @internal
 */
final readonly class Scope
{
    /**
     * @param list<mixed>                     $parameters
     * @param list<ParameterType|string|Type> $types
     */
    private function __construct(
        public string $where,
        public array $parameters,
        public array $types,
        public string $key,
        public bool $reachesNothing,
    ) {
    }

    /**
     * @param ClassMetadata<object> $metadata
     * @param list<string>          $fields
     *
     * @throws InvalidScope when a field is neither a column nor a to-one relation held by one column
     */
    public static function of(EntityManagerInterface $manager, ClassMetadata $metadata, object $record, array $fields): self
    {
        $platform = $manager->getConnection()->getDatabasePlatform();
        $quotes = $manager->getConfiguration()->getQuoteStrategy();
        $conditions = [];
        $parameters = [];
        $types = [];
        $key = [];
        $reachesNothing = false;

        foreach ($fields as $field) {
            if (isset($metadata->fieldMappings[$field])) {
                $column = $quotes->getColumnName($field, $metadata, $platform);
                $value = $metadata->getFieldValue($record, $field);
                $type = $metadata->fieldMappings[$field]->type;

                if ($value instanceof BackedEnum) {
                    $value = $value->value;
                } elseif ($value instanceof UnitEnum) {
                    $value = $value->name;
                }
            } else {
                [$column, $value, $type, $new] = self::relation($manager, $metadata, $record, $field);

                if (null !== $new) {
                    $reachesNothing = true;
                    $key[$field] = [spl_object_id($new)];

                    continue;
                }
            }

            if (null === $value) {
                $conditions[] = $column.' IS NULL';
                $key[$field] = null;

                continue;
            }

            $conditions[] = $column.' = ?';
            $parameters[] = $value;
            $types[] = $type;
            $key[$field] = Type::getType($type)->convertToDatabaseValue($value, $platform);
        }

        return new self([] === $conditions ? '1 = 1' : implode(' AND ', $conditions), $parameters, $types, json_encode($key, \JSON_THROW_ON_ERROR), $reachesNothing);
    }

    /**
     * The table that holds the column of a field: a subclass that inherits it has it in its parent's.
     *
     * @param ClassMetadata<object> $metadata
     */
    public static function tableOf(EntityManagerInterface $manager, ClassMetadata $metadata, string $field): string
    {
        $owner = $manager->getClassMetadata($metadata->fieldMappings[$field]->inherited ?? $metadata->getName());

        return $manager->getConfiguration()->getQuoteStrategy()->getTableName($owner, $manager->getConnection()->getDatabasePlatform());
    }

    /**
     * The join column of a to-one relation, the identifier of the record it points to, its type, and that record when
     * it is yet to be stored.
     *
     * @param ClassMetadata<object> $metadata
     *
     * @return array{string, mixed, string, ?object}
     */
    private static function relation(EntityManagerInterface $manager, ClassMetadata $metadata, object $record, string $field): array
    {
        $association = $metadata->associationMappings[$field] ?? null;

        if (null === $association || !$association->isToOneOwningSide() || 1 !== \count($association->joinColumns)) {
            throw InvalidScope::unknownField($metadata->getName(), $field);
        }

        $joinColumn = $association->joinColumns[0];
        $column = $manager->getConfiguration()->getQuoteStrategy()->getJoinColumnName($joinColumn, $metadata, $manager->getConnection()->getDatabasePlatform());
        $target = $manager->getClassMetadata($association->targetEntity);
        $targetField = $target->getFieldForColumn($joinColumn->referencedColumnName);

        if (!isset($target->fieldMappings[$targetField])) {
            throw InvalidScope::compositeTarget($metadata->getName(), $field);
        }

        $type = $target->fieldMappings[$targetField]->type;
        $related = $metadata->getFieldValue($record, $field);

        if (!\is_object($related)) {
            return [$column, null, $type, null];
        }

        $work = $manager->getUnitOfWork();

        if ($work->isScheduledForInsert($related)) {
            return [$column, null, $type, $related];
        }

        $identifier = $work->isInIdentityMap($related) ? $work->getEntityIdentifier($related) : $target->getIdentifierValues($related);
        $value = $identifier[$targetField] ?? null;

        return null === $value ? [$column, null, $type, $related] : [$column, $value, $type, null];
    }
}

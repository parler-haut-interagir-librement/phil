<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Infrastructure\Move;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\JoinColumnMapping;
use Doctrine\ORM\Mapping\ManyToManyOwningSideMapping;
use Phil\TenancyBundle\Infrastructure\Doctrine\TenantColumn;
use Phil\TenancyDatabaseBundle\Domain\Exception\TenantNotCopyable;

/**
 * The tables that hold a tenant's rows, read from the mapping of an entity manager, in the order their rows can be
 * inserted:
 *
 * 1. the table of each tenant-scoped root entity, after the tenant-scoped tables it references;
 * 2. the table of each subclass of a joined hierarchy, after its parent's;
 * 3. the join table of each many-to-many association owned by a tenant-scoped entity.
 *
 * Deleting goes the other way. A table that is not tenant-scoped — reference data, such as currencies — is not part
 * of a tenant: each database holds its own, from its migrations.
 */
final class CopyPlan
{
    /**
     * @return list<CopyTable>
     *
     * @throws TenantNotCopyable when tenant-scoped tables reference each other, or a table is reached through a key of
     *                           several columns
     */
    public static function of(EntityManagerInterface $entityManager): array
    {
        $platform = $entityManager->getConnection()->getDatabasePlatform();
        $quotes = $entityManager->getConfiguration()->getQuoteStrategy();
        $factory = $entityManager->getMetadataFactory();

        /** @var array<string, array{ClassMetadata<object>, string}> $roots the root entities and their tenant columns, by class */
        $roots = [];

        foreach ($factory->getAllMetadata() as $metadata) {
            if ($metadata->getName() !== $metadata->rootEntityName || $metadata->isMappedSuperclass || $metadata->isEmbeddedClass) {
                continue;
            }

            $column = TenantColumn::of($metadata);

            if (null !== $column) {
                $roots[$metadata->getName()] = [$metadata, $column];
            }
        }

        $tables = [];
        $children = [];
        $joinTables = [];

        foreach (self::ordered($roots, $entityManager) as [$root, $column]) {
            $table = $quotes->getTableName($root, $platform);
            $keys = $quotes->getIdentifierColumnNames($root, $platform);
            $tables[] = new CopyTable($table, \sprintf('%s = ?', $column), $keys, ...self::sequenced($root, $keys));

            $classes = [$root];

            foreach ($root->subClasses as $subclass) {
                $classes[] = $factory->getMetadataFor($subclass);
            }

            foreach ($classes as $class) {
                if ($class !== $root && $root->isInheritanceTypeJoined()) {
                    $childTable = $quotes->getTableName($class, $platform);
                    $childKeys = $quotes->getIdentifierColumnNames($class, $platform);
                    $children[\count($class->parentClasses)][$childTable] = new CopyTable($childTable, self::through($childKeys, $childTable, $table, $keys, $column), $childKeys);
                }

                foreach ($class->associationMappings as $association) {
                    if (!$association instanceof ManyToManyOwningSideMapping) {
                        continue;
                    }

                    $joinTable = $quotes->getJoinTableName($association, $class, $platform);
                    $owner = array_map(static fn (JoinColumnMapping $joinColumn): string => $quotes->getJoinColumnName($joinColumn, $class, $platform), $association->joinTable->joinColumns);
                    $inverse = array_map(static fn (JoinColumnMapping $joinColumn): string => $quotes->getJoinColumnName($joinColumn, $class, $platform), $association->joinTable->inverseJoinColumns);
                    $joinTables[$joinTable] = new CopyTable($joinTable, self::through($owner, $joinTable, $table, $keys, $column), array_merge($owner, $inverse));
                }
            }
        }

        ksort($children);

        foreach ($children as $level) {
            ksort($level);
            array_push($tables, ...array_values($level));
        }

        ksort($joinTables);

        return [...$tables, ...array_values($joinTables)];
    }

    /**
     * The roots, each after the tenant-scoped roots its own table references; ties in the order of their tables.
     *
     * @param array<string, array{ClassMetadata<object>, string}> $roots
     *
     * @return list<array{ClassMetadata<object>, string}>
     */
    private static function ordered(array $roots, EntityManagerInterface $entityManager): array
    {
        $dependencies = [];

        foreach ($roots as $class => [$root]) {
            $dependencies[$class] = self::referenced($root, $roots, $entityManager);
        }

        uksort($dependencies, static fn (string $a, string $b): int => strcmp($roots[$a][0]->getTableName(), $roots[$b][0]->getTableName()));
        $ordered = [];

        while ([] !== $dependencies) {
            $ready = array_keys(array_filter($dependencies, static fn (array $targets): bool => [] === $targets));

            if ([] === $ready) {
                throw TenantNotCopyable::cycle(array_map(static fn (string $class): string => $roots[$class][0]->getTableName(), array_keys($dependencies)));
            }

            foreach ($ready as $class) {
                $ordered[] = $roots[$class];
                unset($dependencies[$class]);
            }

            $dependencies = array_map(static fn (array $targets): array => array_diff_key($targets, array_flip($ready)), $dependencies);
        }

        return $ordered;
    }

    /**
     * The other tenant-scoped roots whose rows the table of a root references.
     *
     * @param ClassMetadata<object>                               $root
     * @param array<string, array{ClassMetadata<object>, string}> $roots
     *
     * @return array<string, true>
     */
    private static function referenced(ClassMetadata $root, array $roots, EntityManagerInterface $entityManager): array
    {
        $factory = $entityManager->getMetadataFactory();
        $classes = [$root];

        // The columns of a single-table hierarchy all live in the root's table.
        if ($root->isInheritanceTypeSingleTable()) {
            foreach ($root->subClasses as $subclass) {
                $classes[] = $factory->getMetadataFor($subclass);
            }
        }

        $referenced = [];

        foreach ($classes as $metadata) {
            foreach ($metadata->associationMappings as $association) {
                $target = $association->isToOneOwningSide() ? $factory->getMetadataFor($association->targetEntity)->rootEntityName : null;

                // A table that references itself is ordered by its key: a parent row usually comes first.
                if (null !== $target && $target !== $root->getName() && isset($roots[$target])) {
                    $referenced[$target] = true;
                }
            }
        }

        return $referenced;
    }

    /**
     * @param list<string> $columns
     * @param list<string> $parentKeys
     */
    private static function through(array $columns, string $table, string $parentTable, array $parentKeys, string $parentColumn): string
    {
        if (1 !== \count($columns) || 1 !== \count($parentKeys)) {
            throw TenantNotCopyable::compositeKey($table);
        }

        return \sprintf('%s IN (SELECT %s FROM %s WHERE %s = ?)', $columns[0], $parentKeys[0], $parentTable, $parentColumn);
    }

    /**
     * The table and the single key column whose sequence must catch up with the copied rows, when the key is generated.
     *
     * @param ClassMetadata<object> $metadata
     * @param list<string>          $keys
     *
     * @return array{}|array{string, string}
     */
    private static function sequenced(ClassMetadata $metadata, array $keys): array
    {
        if (1 !== \count($keys) || (!$metadata->isIdGeneratorIdentity() && !$metadata->isIdGeneratorSequence())) {
            return [];
        }

        return [$metadata->getTableName(), $metadata->getSingleIdentifierColumnName()];
    }
}

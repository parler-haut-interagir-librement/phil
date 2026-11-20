<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;

/**
 * The tables of the mapped classes of one entity manager that implement an interface, each once, with the columns of
 * the given fields, quoted as the mapping says, and the class that maps them there. A subclass that inherits the fields
 * from an entity has them in its parent's table, which the parent gives; a class that does not map the first field is
 * left out.
 *
 * @internal
 */
final class MappedColumns
{
    /**
     * @param class-string           $interface
     * @param non-empty-list<string> $fields
     *
     * @return array<string, array{columns: list<string>, classes: list<class-string>}>
     */
    public static function of(EntityManagerInterface $manager, string $interface, array $fields): array
    {
        $platform = $manager->getConnection()->getDatabasePlatform();
        $quotes = $manager->getConfiguration()->getQuoteStrategy();
        $tables = [];

        /** @var list<ClassMetadata<object>> $all */
        $all = $manager->getMetadataFactory()->getAllMetadata();

        foreach ($all as $metadata) {
            $first = $metadata->fieldMappings[$fields[0]] ?? null;

            if ($metadata->isMappedSuperclass || $metadata->isEmbeddedClass || !is_a($metadata->getName(), $interface, true) || null === $first || null !== $first->inherited) {
                continue;
            }

            $table = $quotes->getTableName($metadata, $platform);
            $tables[$table] ??= ['columns' => array_map(static fn (string $field): string => $quotes->getColumnName($field, $metadata, $platform), $fields), 'classes' => []];
            $tables[$table]['classes'][] = $metadata->getName();
        }

        ksort($tables);

        return $tables;
    }
}

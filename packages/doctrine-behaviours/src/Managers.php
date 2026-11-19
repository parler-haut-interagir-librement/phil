<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * The entity managers an eraser reaches: the one it was given, or every entity manager of a registry. Two managers on
 * one connection share it, and a table both map is reached once on that connection.
 *
 * @internal
 */
final class Managers
{
    /**
     * @return list<EntityManagerInterface>
     */
    public static function all(ManagerRegistry|EntityManagerInterface $managers): array
    {
        if ($managers instanceof EntityManagerInterface) {
            return [$managers];
        }

        return array_values(array_filter($managers->getManagers(), static fn (object $manager): bool => $manager instanceof EntityManagerInterface));
    }

    /**
     * The tables of the mapped classes that implement an interface, grouped by connection: each table once per
     * connection, with its columns and the classes mapped on it in each manager on that connection.
     *
     * @param class-string           $interface
     * @param non-empty-list<string> $fields
     *
     * @return list<array{connection: Connection, managers: list<EntityManagerInterface>, tables: array<string, array{columns: list<string>, classes: list<array{EntityManagerInterface, class-string}>}>}>
     */
    public static function tables(ManagerRegistry|EntityManagerInterface $managers, string $interface, array $fields): array
    {
        $groups = [];

        foreach (self::all($managers) as $manager) {
            $connection = $manager->getConnection();
            $key = spl_object_id($connection);
            $groups[$key] ??= ['connection' => $connection, 'managers' => [], 'tables' => []];
            $groups[$key]['managers'][] = $manager;

            foreach (MappedColumns::of($manager, $interface, $fields) as $table => ['columns' => $columns, 'classes' => $classes]) {
                $groups[$key]['tables'][$table] ??= ['columns' => $columns, 'classes' => []];

                foreach ($classes as $class) {
                    $groups[$key]['tables'][$table]['classes'][] = [$manager, $class];
                }
            }
        }

        return array_values($groups);
    }

    /**
     * The classes mapped on the tables of a group, by manager.
     *
     * @param array{connection: Connection, managers: list<EntityManagerInterface>, tables: array<string, array{columns: list<string>, classes: list<array{EntityManagerInterface, class-string}>}>} $group
     *
     * @return list<array{EntityManagerInterface, class-string}>
     */
    public static function classes(array $group): array
    {
        $classes = [];

        foreach ($group['tables'] as ['classes' => $mapped]) {
            $classes = [...$classes, ...$mapped];
        }

        return $classes;
    }

    /**
     * The names of the tables of every group, each once, sorted.
     *
     * @param list<array{connection: Connection, managers: list<EntityManagerInterface>, tables: array<string, array{columns: list<string>, classes: list<array{EntityManagerInterface, class-string}>}>}> $groups
     *
     * @return list<string>
     */
    public static function tableNames(array $groups): array
    {
        $tables = [];

        foreach ($groups as $group) {
            foreach (array_keys($group['tables']) as $table) {
                $tables[] = $table;
            }
        }

        $tables = array_values(array_unique($tables));
        sort($tables);

        return $tables;
    }
}

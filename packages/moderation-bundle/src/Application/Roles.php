<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Application;

/**
 * The roles of the moderation, in the order they are tried: a member who holds several acts under the first.
 */
final readonly class Roles
{
    /** @var array<string, Role> */
    private array $roles;

    /**
     * @param iterable<Role> $roles
     */
    public function __construct(iterable $roles)
    {
        $byName = [];

        foreach ($roles as $role) {
            $byName[$role->name] = $role;
        }

        $this->roles = $byName;
    }

    /**
     * @return list<Role>
     */
    public function all(): array
    {
        return array_values($this->roles);
    }

    public function get(string $name): ?Role
    {
        return $this->roles[$name] ?? null;
    }

    /**
     * The types of targets the roles cover between them, or an empty list when one covers every type.
     *
     * @param list<Role> $roles
     *
     * @return list<string>
     */
    public static function typesOf(array $roles): array
    {
        $types = [];

        foreach ($roles as $role) {
            if ([] === $role->types) {
                return [];
            }

            array_push($types, ...$role->types);
        }

        return array_values(array_unique($types));
    }
}

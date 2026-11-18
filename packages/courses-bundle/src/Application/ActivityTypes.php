<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Application;

use Phil\CoursesBundle\Application\Contract\ActivityType;
use Phil\CoursesBundle\Domain\Exception\InvalidCourse;

/**
 * The activity types the application has, by name.
 */
final readonly class ActivityTypes
{
    /** @var array<string, ActivityType> */
    private array $types;

    /**
     * @param iterable<ActivityType> $types
     */
    public function __construct(iterable $types)
    {
        $named = [];

        foreach ($types as $type) {
            $named[$type->name()] = $type;
        }

        $this->types = $named;
    }

    /**
     * @throws InvalidCourse when no type has that name
     */
    public function get(string $name): ActivityType
    {
        return $this->types[$name] ?? throw InvalidCourse::because(\sprintf('No activity type is named "%s"; the types are: %s.', $name, implode(', ', $this->names())));
    }

    public function has(string $name): bool
    {
        return isset($this->types[$name]);
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_map(strval(...), array_keys($this->types));
    }
}

<?php

declare(strict_types=1);

namespace Phil\ReviewsBundle\Application;

use Phil\ReviewsBundle\Domain\Exception\InvalidReview;

/**
 * The types of subject the configuration declares: a review of another type is refused.
 */
final readonly class SubjectTypes
{
    /** @var array<string, SubjectType> */
    private array $types;

    public function __construct(SubjectType ...$types)
    {
        $byName = [];

        foreach ($types as $type) {
            $byName[$type->name] = $type;
        }

        $this->types = $byName;
    }

    /**
     * @throws InvalidReview when no type of that name is declared
     */
    public function get(string $name): SubjectType
    {
        return $this->types[$name] ?? throw InvalidReview::because(\sprintf('"%s" is not a type of subject the reviews declare.', $name));
    }

    public function has(string $name): bool
    {
        return isset($this->types[$name]);
    }

    /**
     * @return list<SubjectType>
     */
    public function all(): array
    {
        return array_values($this->types);
    }
}

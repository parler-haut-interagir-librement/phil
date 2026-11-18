<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Domain\ValueObject;

use Phil\OrganizationsBundle\Domain\Exception\InvalidOrganization;

/**
 * The roles of a membership: one or more role names, each 1 to 64 lowercase letters, digits or "_", starting with a
 * letter, at most sixteen, kept sorted and without repeats so that two sets of the same roles are equal.
 */
final readonly class Roles
{
    public const int MAX = 16;

    public const string NAME_PATTERN = '/^[a-z][a-z0-9_]{0,63}$/D';

    /**
     * @param non-empty-list<non-empty-string> $names
     */
    private function __construct(
        public array $names,
    ) {
    }

    /**
     * @param iterable<mixed> $names
     *
     * @throws InvalidOrganization when no role is given, more than sixteen are, or a name is out of shape
     */
    public static function of(iterable $names): self
    {
        $roles = [];

        foreach ($names as $name) {
            if (!\is_string($name) || 1 !== preg_match(self::NAME_PATTERN, $name, $matches)) {
                throw InvalidOrganization::because(\sprintf('A role is 1 to 64 lowercase letters, digits or "_", starting with a letter; "%s" is not.', \is_scalar($name) ? (string) $name : get_debug_type($name)));
            }

            $roles[$matches[0]] = $matches[0];
        }

        if ([] === $roles) {
            throw InvalidOrganization::because('A membership holds at least one role.');
        }

        if (\count($roles) > self::MAX) {
            throw InvalidOrganization::because(\sprintf('A membership holds at most %d roles.', self::MAX));
        }

        ksort($roles, \SORT_STRING);

        return new self(array_values($roles));
    }

    public function has(string $role): bool
    {
        return \in_array($role, $this->names, true);
    }

    public function equals(self $other): bool
    {
        return $this->names === $other->names;
    }

    /**
     * The roles written as one text, separated by spaces, as a store keeps them.
     */
    public function toString(): string
    {
        return implode(' ', $this->names);
    }

    /**
     * The roles `toString()` wrote.
     */
    public static function fromString(string $roles): self
    {
        return self::of(explode(' ', $roles));
    }
}

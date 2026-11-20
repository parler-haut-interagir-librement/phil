<?php

declare(strict_types=1);

namespace Phil\ApiKeysBundle\Application;

use Phil\ApiKeysBundle\Domain\Exception\InvalidTokenValue;
use Phil\ApiKeysBundle\Domain\Token\TokenOwner;

/**
 * The owner types a token may act for, with the scopes each may hold: `user`, the members, always, and the others the
 * configuration declares — `organization`, `application`… A type without a list of its own may hold every declared
 * scope.
 */
final readonly class OwnerTypes
{
    /** @var array<string, list<string>|null> */
    private array $types;

    /**
     * @param array<string, list<string>|null> $types the scopes each type may hold by type, null for every scope
     *
     * @throws InvalidTokenValue when a type is out of shape
     */
    public function __construct(array $types = [])
    {
        $checked = [TokenOwner::USER => null];

        foreach ($types as $type => $scopes) {
            if (1 !== preg_match(TokenOwner::TYPE_PATTERN, $type)) {
                throw InvalidTokenValue::because(\sprintf('An owner type is a lowercase letter followed by up to 31 lowercase letters, digits or "_"; "%s" is not.', $type));
            }

            $checked[$type] = $scopes;
        }

        $this->types = $checked;
    }

    public function has(string $type): bool
    {
        return \array_key_exists($type, $this->types);
    }

    /**
     * @return list<string> the declared types, `user` first
     */
    public function names(): array
    {
        return array_keys($this->types);
    }

    /**
     * The scopes the tokens of a type may hold, null for every declared one.
     *
     * @return list<string>|null
     *
     * @throws InvalidTokenValue when the type is not declared
     */
    public function scopes(string $type): ?array
    {
        if (!$this->has($type)) {
            throw InvalidTokenValue::because(\sprintf('The owner type "%s" is not declared; the declared types are: %s.', $type, implode(', ', $this->names())));
        }

        return $this->types[$type];
    }

    /**
     * Checks that the owner's type is declared and may hold each scope.
     *
     * @param list<string> $scopes
     *
     * @throws InvalidTokenValue when the type is not declared, or a scope is not one its tokens may hold
     */
    public function check(TokenOwner $owner, array $scopes): void
    {
        $allowed = $this->scopes($owner->type);

        if (null === $allowed) {
            return;
        }

        foreach ($scopes as $scope) {
            if (!\in_array($scope, $allowed, true)) {
                throw InvalidTokenValue::because(\sprintf('A token of the owner type "%s" does not hold the scope "%s".', $owner->type, $scope));
            }
        }
    }
}

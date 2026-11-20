<?php

declare(strict_types=1);

namespace Phil\ApiKeysBundle\Application;

use Phil\ApiKeysBundle\Application\Exception\UnknownScope;
use Phil\ApiKeysBundle\Domain\Exception\InvalidTokenValue;

/**
 * The scopes the configuration declares, in its order. A token holds only scopes of this list.
 */
final readonly class ScopeCatalogue
{
    public const string NAME_PATTERN = '/^[a-z][a-z0-9_.:-]{0,63}$/D';

    /** @var array<string, ScopeDefinition> */
    private array $scopes;

    /**
     * @param array<array-key, array{description: string, label?: string|null}> $scopes the declared scopes by name
     *
     * @throws InvalidTokenValue when a name is not a lowercase letter followed by up to 63 letters, digits, "_", ".",
     *                           ":" or "-"
     */
    public function __construct(array $scopes)
    {
        $definitions = [];

        foreach ($scopes as $name => $scope) {
            $name = (string) $name;

            if (1 !== preg_match(self::NAME_PATTERN, $name)) {
                throw InvalidTokenValue::because(\sprintf('A scope name is a lowercase letter followed by up to 63 lowercase letters, digits, "_", ".", ":" or "-"; "%s" is not.', $name));
            }

            $definitions[$name] = new ScopeDefinition($name, $scope['description'], $scope['label'] ?? 'scope.'.$name);
        }

        $this->scopes = $definitions;
    }

    public function has(string $name): bool
    {
        return isset($this->scopes[$name]);
    }

    /**
     * @return list<ScopeDefinition>
     */
    public function all(): array
    {
        return array_values($this->scopes);
    }

    /**
     * The scopes asked for, each once, in the order of the catalogue.
     *
     * @param array<array-key, string> $names
     *
     * @return list<string>
     *
     * @throws UnknownScope when one of them is not declared
     */
    public function select(array $names): array
    {
        foreach ($names as $name) {
            if (!$this->has($name)) {
                throw UnknownScope::named($name, array_keys($this->scopes));
            }
        }

        return array_values(array_filter(array_keys($this->scopes), static fn (string $name): bool => \in_array($name, $names, true)));
    }
}

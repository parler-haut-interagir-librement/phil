<?php

declare(strict_types=1);

namespace Phil\ConsoleBundle\Domain\Attribute;

use Phil\ConsoleBundle\Domain\Exception\InvalidGuard;

/**
 * The environment names of a guard attribute, checked and without repetition.
 *
 * @internal
 */
final class EnvironmentNames
{
    /**
     * @param class-string             $attribute
     * @param array<array-key, string> $environments
     *
     * @return list<string>
     *
     * @throws InvalidGuard With a name that is empty or holds a space
     */
    public static function of(string $attribute, array $environments): array
    {
        $names = [];

        foreach ($environments as $environment) {
            if (1 !== preg_match('/^[^\s]+$/D', $environment)) {
                throw InvalidGuard::withInvalidEnvironment($attribute, $environment);
            }

            $names[$environment] = $environment;
        }

        return array_values($names);
    }
}

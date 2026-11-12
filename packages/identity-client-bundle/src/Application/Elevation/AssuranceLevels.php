<?php

declare(strict_types=1);

namespace Phil\IdentityClientBundle\Application\Elevation;

/**
 * The assurance scale of the provider, ordered from the weakest authentication context class to the strongest.
 */
final readonly class AssuranceLevels
{
    /** @var array<string, AssuranceLevel> */
    private array $levels;

    /** @var array<string, int> the rank of each authentication context class */
    private array $ranks;

    /**
     * @param list<AssuranceLevel> $levels weakest first
     */
    public function __construct(array $levels)
    {
        $byName = [];
        $ranks = [];
        foreach ($levels as $level) {
            $byName[$level->name] = $level;
            if (null !== $level->acr && !isset($ranks[$level->acr])) {
                $ranks[$level->acr] = \count($ranks);
            }
        }

        $this->levels = $byName;
        $this->ranks = $ranks;
    }

    public function get(string $name): ?AssuranceLevel
    {
        return $this->levels[$name] ?? null;
    }

    /**
     * Whether an authentication asserted at $acr reaches the class the level asks for.
     */
    public function reaches(AssuranceLevel $level, ?string $acr): bool
    {
        if (null === $level->acr) {
            return true;
        }

        return null !== $acr && isset($this->ranks[$acr]) && $this->ranks[$acr] >= $this->ranks[$level->acr];
    }

    /**
     * The classes that satisfy the level, for `acr_values`: its own, then the stronger ones.
     *
     * @return list<string>
     */
    public function acceptableClasses(AssuranceLevel $level): array
    {
        return null === $level->acr ? [] : \array_slice(array_keys($this->ranks), $this->ranks[$level->acr]);
    }
}

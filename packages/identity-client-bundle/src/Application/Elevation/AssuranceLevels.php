<?php

declare(strict_types=1);

namespace Phil\IdentityClientBundle\Application\Elevation;

use InvalidArgumentException;

/**
 * The assurance scale of the provider, ordered from the weakest authentication context class to the strongest, and the
 * level every sign-in has to reach, when there is one.
 */
final readonly class AssuranceLevels
{
    /** @var array<string, AssuranceLevel> */
    private array $levels;

    /** @var array<string, int> the rank of each authentication context class */
    private array $ranks;

    private ?AssuranceLevel $required;

    /**
     * @param list<AssuranceLevel> $levels   weakest first
     * @param ?string              $required the name of the level every sign-in has to reach; it needs an acr
     *
     * @throws InvalidArgumentException when the required level is not on the scale, or has no acr
     */
    public function __construct(array $levels, ?string $required = null)
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
        $this->required = null === $required ? null : $byName[$required] ?? null;
        if (null !== $required && null === $this->required?->acr) {
            throw new InvalidArgumentException(\sprintf('The required assurance level "%s" must be a level of the scale with an acr.', $required));
        }
    }

    /**
     * The level every sign-in has to reach, or null when any authentication will do.
     */
    public function required(): ?AssuranceLevel
    {
        return $this->required;
    }

    /**
     * Whether an authentication asserted at $acr reaches the required level; true when there is none.
     */
    public function reachesRequired(?string $acr): bool
    {
        return !$this->required instanceof AssuranceLevel || $this->reaches($this->required, $acr);
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

    /**
     * The classes an authorization request asks for: those of the level asked for, narrowed to the ones that also
     * reach the required level, or those of the required level alone when no level with a class is asked for.
     *
     * @return list<string>
     */
    public function requestedClasses(?AssuranceLevel $level): array
    {
        $asked = $level instanceof AssuranceLevel ? $this->acceptableClasses($level) : [];
        $required = $this->required instanceof AssuranceLevel ? $this->acceptableClasses($this->required) : [];

        return [] === $asked || ([] !== $required && \count($required) < \count($asked)) ? $required : $asked;
    }
}

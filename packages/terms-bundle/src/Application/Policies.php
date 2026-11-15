<?php

declare(strict_types=1);

namespace Phil\TermsBundle\Application;

use Phil\TermsBundle\Domain\Exception\InvalidTerms;
use Phil\TermsBundle\Domain\ValueObject\Policy;

/**
 * The policies the application declares, in the order of its configuration: the order the acceptance page lists them
 * in.
 */
final readonly class Policies
{
    /**
     * @var array<string, Policy>
     */
    private array $byKey;

    public function __construct(Policy ...$policies)
    {
        $byKey = [];

        foreach ($policies as $policy) {
            $byKey[$policy->key] = $policy;
        }

        $this->byKey = $byKey;
    }

    /**
     * @throws InvalidTerms when the application declares no such policy
     */
    public function get(string $key): Policy
    {
        return $this->byKey[$key] ?? throw InvalidTerms::because('unknown_policy', 'No policy "%key%" is declared.', ['%key%' => mb_substr($key, 0, 80)]);
    }

    public function has(string $key): bool
    {
        return isset($this->byKey[$key]);
    }

    /**
     * @return list<Policy>
     */
    public function all(): array
    {
        return array_values($this->byKey);
    }

    /**
     * The policies a signed-in member must have accepted to go on.
     *
     * @return list<Policy>
     */
    public function required(): array
    {
        return array_values(array_filter($this->byKey, static fn (Policy $policy): bool => $policy->required));
    }

    /**
     * The policy whose versions name the licence of the members' contributions, if the application declares one.
     */
    public function contributionLicence(): ?Policy
    {
        return array_find($this->byKey, static fn (Policy $policy): bool => $policy->contributionLicence);
    }
}

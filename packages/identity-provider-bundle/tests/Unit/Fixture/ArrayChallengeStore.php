<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Tests\Unit\Fixture;

use Override;
use Phil\IdentityProviderBundle\Application\Contract\ChallengeStore;

/**
 * Challenges in an array, for the tests.
 */
final class ArrayChallengeStore implements ChallengeStore
{
    /** @var array<string, string> */
    public array $values = [];

    #[Override]
    public function put(string $purpose, string $value): void
    {
        $this->values[$purpose] = $value;
    }

    #[Override]
    public function take(string $purpose): ?string
    {
        $value = $this->values[$purpose] ?? null;
        unset($this->values[$purpose]);

        return $value;
    }

    #[Override]
    public function peek(string $purpose): ?string
    {
        return $this->values[$purpose] ?? null;
    }
}

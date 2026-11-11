<?php

declare(strict_types=1);

namespace Phil\Passwords\Generator;

use Random\Engine\Secure;
use Random\Randomizer;

/**
 * Generates random passwords from a policy, every character drawn uniformly from the cryptographically secure source
 * of the system. With each set required, a draw that misses one is drawn again whole, so that every password of the
 * policy stays equally likely; the expected number of draws stays small for any length the policy allows.
 *
 * The generator holds no state between two calls; a test gives it a seeded randomizer.
 */
final readonly class PasswordGenerator
{
    public function __construct(
        private Randomizer $randomizer = new Randomizer(new Secure()),
    ) {
    }

    public function generate(PasswordPolicy $policy = new PasswordPolicy()): string
    {
        $alphabets = $policy->alphabets();
        /** @var non-empty-string $alphabet the policy draws from at least one set */
        $alphabet = implode('', $alphabets);
        /** @var positive-int $length the policy's length is at least MIN_LENGTH */
        $length = $policy->length;

        do {
            $password = $this->randomizer->getBytesFromString($alphabet, $length);
        } while ($policy->requireEachSet && !$this->holdsEach($password, $alphabets));

        return $password;
    }

    /**
     * @param list<string> $alphabets
     */
    private function holdsEach(string $password, array $alphabets): bool
    {
        return array_all($alphabets, static fn (string $alphabet): bool => false !== strpbrk($password, $alphabet));
    }
}

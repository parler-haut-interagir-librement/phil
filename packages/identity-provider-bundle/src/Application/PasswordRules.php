<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Application;

use Phil\IdentityProviderBundle\Domain\Model\Account\Account;
use Phil\Passwords\Breach\BreachChecker;
use Phil\Passwords\Strength\Score;
use Phil\Passwords\Strength\StrengthEstimator;
use SensitiveParameter;

/**
 * What a new password must be, through `phil/passwords`: 8 to 4096 characters — the bound keeps a hash from becoming
 * a denial of service —, a score from the strength estimator of at least the minimum, the account's address and name
 * counted as the first guesses, and, when a breach source is configured, absent from known breaches. A breach source
 * that does not answer does not refuse the password: the check is a safeguard, not a gate on availability.
 */
final readonly class PasswordRules
{
    public const int MIN_LENGTH = 8;

    public const int MAX_LENGTH = 4096;

    public function __construct(
        private StrengthEstimator $estimator,
        private Score $minimum = Score::Fair,
        private ?BreachChecker $breaches = null,
    ) {
    }

    /**
     * The translation keys of what the password breaks, none when it is accepted.
     *
     * @return list<string>
     */
    public function problems(#[SensitiveParameter] string $password, ?Account $account = null, ?string $email = null): array
    {
        $length = mb_strlen($password);

        if ($length < self::MIN_LENGTH) {
            return ['password.too_short'];
        }

        if ($length > self::MAX_LENGTH) {
            return ['password.too_long'];
        }

        $inputs = array_values(array_filter([$email, $account?->email(), $account?->displayName()], static fn (?string $input): bool => null !== $input));

        if (!$this->estimator->estimate($password, $inputs)->score->isAtLeast($this->minimum)) {
            return ['password.too_weak'];
        }

        if (true === $this->breaches?->check($password)->isCompromised()) {
            return ['password.breached'];
        }

        return [];
    }
}

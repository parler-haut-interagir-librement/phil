<?php

declare(strict_types=1);

namespace Phil\Passwords\Validator;

use Attribute;
use Phil\Passwords\Strength\Score;
use Symfony\Component\Validator\Constraint;

/**
 * The value, a password, is scored at least `minScore` by the strength estimator. When the object validated implements
 * `PasswordUserInputs`, its inputs are taken into account. `null` and the empty string are valid, as for every
 * constraint but `NotBlank`.
 *
 * The violation's parameters are the score, the minimum, and the translation keys of the warning and of the first
 * suggestion, in the `phil_passwords` domain, for the form to show the feedback.
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class MinimumStrength extends Constraint
{
    public const string TOO_WEAK_ERROR = '6f2c8d41-93b7-4e05-a1d6-2b7e95c3f0a8';

    protected const array ERROR_NAMES = [
        self::TOO_WEAK_ERROR => 'TOO_WEAK_ERROR',
    ];

    /**
     * @param ?list<string> $groups
     */
    public function __construct(
        public Score $minScore = Score::Strong,
        public string $message = 'This password is too easy to guess.',
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct(null, $groups, $payload);
    }
}

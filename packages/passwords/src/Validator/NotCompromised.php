<?php

declare(strict_types=1);

namespace Phil\Passwords\Validator;

use Attribute;
use Symfony\Component\Validator\Constraint;

/**
 * The value, a password, appears in known data breaches fewer times than `threshold`, by the breach checker the
 * validator is built with. When the source cannot answer, the password is refused with `unavailableMessage` — failing
 * closed —, unless `skipOnError` accepts it. `null` and the empty string are valid, as for every constraint but
 * `NotBlank`.
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class NotCompromised extends Constraint
{
    public const string COMPROMISED_ERROR = 'c3a97e15-48d2-4b6f-9e01-7d5f2a8b4c63';

    public const string UNAVAILABLE_ERROR = '0b8e4f6a-2d71-4c93-b5e8-91a3c6d7f204';

    protected const array ERROR_NAMES = [
        self::COMPROMISED_ERROR => 'COMPROMISED_ERROR',
        self::UNAVAILABLE_ERROR => 'UNAVAILABLE_ERROR',
    ];

    /**
     * @param positive-int  $threshold
     * @param ?list<string> $groups
     */
    public function __construct(
        public int $threshold = 1,
        public bool $skipOnError = false,
        public string $message = 'This password appears in known data breaches: choose another one.',
        public string $unavailableMessage = 'This password could not be checked against known data breaches: try again later.',
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct(null, $groups, $payload);
    }
}

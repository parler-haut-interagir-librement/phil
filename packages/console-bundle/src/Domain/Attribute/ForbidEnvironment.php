<?php

declare(strict_types=1);

namespace Phil\ConsoleBundle\Domain\Attribute;

use Attribute;
use Phil\ConsoleBundle\Domain\Exception\InvalidGuard;

/**
 * Refuses to run a command in the listed kernel environments:
 *
 * ```php
 * #[AsCommand('app:database:reset')]
 * #[ForbidEnvironment('prod', 'staging')]
 * final class ResetDatabase
 * ```
 *
 * It is read on the command's class and on the method the command runs — `__invoke()` or a method-level command —,
 * and the environments of every occurrence add up.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class ForbidEnvironment
{
    /**
     * @var list<string>
     */
    public array $environments;

    /**
     * @throws InvalidGuard Without an environment, or with an empty name
     */
    public function __construct(string ...$environments)
    {
        if ([] === $environments) {
            throw InvalidGuard::withoutEnvironment(self::class);
        }

        $this->environments = EnvironmentNames::of(self::class, $environments);
    }
}

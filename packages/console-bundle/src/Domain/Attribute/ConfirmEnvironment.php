<?php

declare(strict_types=1);

namespace Phil\ConsoleBundle\Domain\Attribute;

use Attribute;
use Phil\ConsoleBundle\Domain\Exception\InvalidGuard;

/**
 * Asks for the kernel environment's name before a command runs, and refuses a non-interactive run unless
 * `--force-environment` is given:
 *
 * ```php
 * #[AsCommand('app:customers:anonymize')]
 * #[ConfirmEnvironment('prod')]
 * final class AnonymizeCustomers
 * ```
 *
 * Without an environment, the confirmation is asked in every environment. It is read on the command's class and on the
 * method the command runs, and the environments of every occurrence add up.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class ConfirmEnvironment
{
    /**
     * @var list<string> The environments that ask for a confirmation; empty for all
     */
    public array $environments;

    /**
     * @throws InvalidGuard With an empty name
     */
    public function __construct(string ...$environments)
    {
        $this->environments = EnvironmentNames::of(self::class, $environments);
    }
}

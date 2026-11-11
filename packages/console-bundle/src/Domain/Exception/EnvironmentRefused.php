<?php

declare(strict_types=1);

namespace Phil\ConsoleBundle\Domain\Exception;

use RuntimeException;

/**
 * A command refused in the current kernel environment: forbidden there, or not confirmed. Its code, 77, is the exit
 * code of the run — `EX_NOPERM` of sysexits.h.
 */
final class EnvironmentRefused extends RuntimeException implements ConsoleException
{
    public const int EXIT_CODE = 77;

    public static function forbidden(string $command, string $environment): self
    {
        return new self(\sprintf('The command "%s" cannot run in the "%s" environment.', $command, $environment), self::EXIT_CODE);
    }

    public static function notInteractive(string $command, string $environment): self
    {
        return new self(\sprintf('The command "%s" asks for a confirmation in the "%s" environment: run it interactively, or add --force-environment.', $command, $environment), self::EXIT_CODE);
    }

    public static function notConfirmed(string $command, string $environment): self
    {
        return new self(\sprintf('The command "%s" did not run: the answer was not "%s".', $command, $environment), self::EXIT_CODE);
    }
}

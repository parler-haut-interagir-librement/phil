<?php

declare(strict_types=1);

namespace Phil\ConsoleBundle\Tests\Unit\Domain;

use Phil\ConsoleBundle\Domain\Exception\EnvironmentRefused;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EnvironmentRefused::class)]
final class EnvironmentRefusedTest extends TestCase
{
    public function testTheMessagesAndTheExitCode(): void
    {
        $forbidden = EnvironmentRefused::forbidden('app:reset', 'prod');
        self::assertSame('The command "app:reset" cannot run in the "prod" environment.', $forbidden->getMessage());
        self::assertSame(77, $forbidden->getCode());
        self::assertSame('The command "app:reset" asks for a confirmation in the "prod" environment: run it interactively, or add --force-environment.', EnvironmentRefused::notInteractive('app:reset', 'prod')->getMessage());
        self::assertSame('The command "app:reset" did not run: the answer was not "prod".', EnvironmentRefused::notConfirmed('app:reset', 'prod')->getMessage());
    }
}

<?php

declare(strict_types=1);

namespace Phil\ConsoleBundle\Tests\Unit\Fixture;

use Phil\ConsoleBundle\Domain\Attribute\ForbidEnvironment;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;

#[AsCommand('test:invokable-class')]
final class InvokableClassCommand extends Command
{
    #[ForbidEnvironment('prod')]
    public function __invoke(): int
    {
        return 0;
    }
}

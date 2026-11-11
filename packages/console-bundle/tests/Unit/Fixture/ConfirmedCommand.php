<?php

declare(strict_types=1);

namespace Phil\ConsoleBundle\Tests\Unit\Fixture;

use Phil\ConsoleBundle\Domain\Attribute\ConfirmEnvironment;
use Phil\ConsoleBundle\Domain\Attribute\ForbidEnvironment;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('test:confirmed')]
#[ForbidEnvironment('staging')]
final class ConfirmedCommand
{
    #[ConfirmEnvironment('prod')]
    #[ForbidEnvironment('dev')]
    public function __invoke(OutputInterface $output): int
    {
        $output->writeln('ran');

        return 0;
    }
}

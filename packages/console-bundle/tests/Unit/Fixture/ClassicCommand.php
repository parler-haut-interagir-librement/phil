<?php

declare(strict_types=1);

namespace Phil\ConsoleBundle\Tests\Unit\Fixture;

use Override;
use Phil\ConsoleBundle\Domain\Attribute\ConfirmEnvironment;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('test:classic')]
#[ConfirmEnvironment]
final class ClassicCommand extends Command
{
    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->write('one ');
        $output->writeln('line');
        $output->writeln("two\nlines");

        return 3;
    }
}

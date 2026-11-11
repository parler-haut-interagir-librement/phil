<?php

declare(strict_types=1);

namespace Phil\ConsoleBundle\Tests\Unit\Fixture;

use LogicException;
use Override;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\ConsoleSectionOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * A console output whose standard output is a buffer, and whose error output is another output.
 */
final class BufferedConsoleOutput extends BufferedOutput implements ConsoleOutputInterface
{
    public function __construct(
        private OutputInterface $errors,
    ) {
        parent::__construct();
    }

    #[Override]
    public function getErrorOutput(): OutputInterface
    {
        return $this->errors;
    }

    #[Override]
    public function setErrorOutput(OutputInterface $error): void
    {
        $this->errors = $error;
    }

    #[Override]
    public function section(): ConsoleSectionOutput
    {
        throw new LogicException('No section in a buffer.');
    }
}

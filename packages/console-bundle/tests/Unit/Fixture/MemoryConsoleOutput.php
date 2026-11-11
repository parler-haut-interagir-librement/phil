<?php

declare(strict_types=1);

namespace Phil\ConsoleBundle\Tests\Unit\Fixture;

use LogicException;
use Override;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\ConsoleSectionOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;

/**
 * A console output whose standard and error outputs write to memory.
 */
final class MemoryConsoleOutput extends StreamOutput implements ConsoleOutputInterface
{
    private OutputInterface $errors;

    public function __construct(int $verbosity = self::VERBOSITY_NORMAL, bool $decorated = false)
    {
        $out = fopen('php://memory', 'w+');
        $err = fopen('php://memory', 'w+');

        if (false === $out || false === $err) {
            throw new LogicException('No memory stream.');
        }

        parent::__construct($out, $verbosity, $decorated);
        $this->errors = new StreamOutput($err, $verbosity, $decorated);
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
        throw new LogicException('No section in memory.');
    }

    public function display(): string
    {
        return $this->read($this);
    }

    public function errors(): string
    {
        return $this->errors instanceof StreamOutput ? $this->read($this->errors) : '';
    }

    private function read(StreamOutput $output): string
    {
        rewind($output->getStream());

        return (string) stream_get_contents($output->getStream());
    }
}

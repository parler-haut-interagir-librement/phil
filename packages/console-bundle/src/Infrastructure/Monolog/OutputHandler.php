<?php

declare(strict_types=1);

namespace Phil\ConsoleBundle\Infrastructure\Monolog;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\LogRecord;
use Override;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Writes each record, from the debug level up, to a console output as raw text: a message is never read as console
 * markup.
 */
final class OutputHandler extends AbstractProcessingHandler
{
    /**
     * The level and the bubbling are the parent's defaults: every record, passed on to the next handler.
     */
    public function __construct(
        private readonly OutputInterface $output,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function write(LogRecord $record): void
    {
        $this->output->write(\is_string($record->formatted) ? $record->formatted : $record->message."\n", false, OutputInterface::OUTPUT_RAW);
    }
}

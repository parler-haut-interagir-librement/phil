<?php

declare(strict_types=1);

namespace Phil\ConsoleBundle\Tests\Unit\Fixture;

use Symfony\Component\Console\Command\Command;

/**
 * A command named as FrameworkBundle's traced command, outside the console component.
 */
final class TraceableCommand extends Command
{
    public function __construct(
        public readonly Command $command,
    ) {
        parent::__construct('test:traceable');
    }
}

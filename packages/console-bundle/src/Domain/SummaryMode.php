<?php

declare(strict_types=1);

namespace Phil\ConsoleBundle\Domain;

/**
 * When the duration and memory summary is written at the end of a command.
 */
enum SummaryMode: string
{
    case Always = 'always';
    case Verbose = 'verbose';
    case Never = 'never';

    public function shows(bool $verbose): bool
    {
        return self::Always === $this || (self::Verbose === $this && $verbose);
    }
}

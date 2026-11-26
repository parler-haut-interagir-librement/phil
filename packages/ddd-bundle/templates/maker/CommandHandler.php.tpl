<?php

declare(strict_types=1);

namespace {{namespace}};

use Phil\DddBundle\Application\Bus\CommandHandler;

/**
 * Handles {{message}}, in the transaction of the command bus.
 */
final readonly class {{class}} implements CommandHandler
{
    public function __invoke({{message}} $command): void
    {
    }
}

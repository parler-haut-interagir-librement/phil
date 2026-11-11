<?php

declare(strict_types=1);

namespace Phil\DddBundle\Application\Bus;

use Phil\DddBundle\Domain\Exception\DomainError;

interface CommandBus
{
    /**
     * @throws DomainError if an invariant refuses the command
     */
    public function dispatch(Command $command): void;
}

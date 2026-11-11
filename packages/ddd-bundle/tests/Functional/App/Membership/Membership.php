<?php

declare(strict_types=1);

namespace Phil\DddBundle\Tests\Functional\App\Membership;

use Phil\DddBundle\Application\Bus\CommandBus;
use Phil\DddBundle\Application\Bus\QueryBus;

/**
 * An application service that knows the two ports and nothing of Messenger: autowired by their interfaces.
 */
final readonly class Membership
{
    public function __construct(
        public CommandBus $commands,
        public QueryBus $queries,
    ) {
    }
}

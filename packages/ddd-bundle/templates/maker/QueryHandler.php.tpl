<?php

declare(strict_types=1);

namespace {{namespace}};

use Phil\DddBundle\Application\Bus\QueryHandler;

/**
 * Answers {{message}}: reads the answer, a DTO, without loading an aggregate.
 */
final readonly class {{class}} implements QueryHandler
{
    public function __invoke({{message}} $query): null
    {
        return null;
    }
}

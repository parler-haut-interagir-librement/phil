<?php

declare(strict_types=1);

namespace Phil\StructuredData\Contract;

use Phil\StructuredData\Node;

/**
 * Where the parts of an application pour the nodes of a page: the graph of that page gathers them, merged by `@id`,
 * and one script element prints it.
 */
interface NodeCollector
{
    public function add(Node ...$nodes): void;
}

<?php

declare(strict_types=1);

namespace Phil\StructuredDataBundle\Application\Contract;

use Phil\StructuredData\Node;
use Phil\StructuredDataBundle\Application\PageContext;

/**
 * Gives nodes to the graph of every page: a package's own description of the site, of the current page, of what the
 * page shows. A provider is asked once per page, when the graph is first printed; the nodes it gives merge by `@id`
 * with the others.
 *
 * A class that implements it is autoconfigured; providers run after the nodes of the configuration, in the order of
 * their `before` and `after` constraints.
 */
interface StructuredDataProvider
{
    /**
     * @return iterable<Node>
     */
    public function nodes(PageContext $page): iterable;
}

<?php

declare(strict_types=1);

namespace Phil\StructuredDataBundle\Application;

use Phil\IndexingConsent\Indexing;
use Phil\StructuredData\Graph;

/**
 * @internal what one page has gathered: the nodes added to it, those of the providers once they have been asked, and
 *           the reach of the records it shows
 */
final class PageState
{
    public Graph $added;

    public ?Graph $provided = null;

    public Indexing $indexing = Indexing::Listed;

    public function __construct(public readonly PageContext $context)
    {
        $this->added = Graph::of();
    }
}

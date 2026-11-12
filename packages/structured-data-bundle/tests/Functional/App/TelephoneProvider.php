<?php

declare(strict_types=1);

namespace Phil\StructuredDataBundle\Tests\Functional\App;

use Override;
use Phil\StructuredData\Node;
use Phil\StructuredDataBundle\Application\Contract\StructuredDataProvider;
use Phil\StructuredDataBundle\Application\PageContext;

/**
 * A provider of the application, which adds a telephone to the organisation of the configuration.
 */
final class TelephoneProvider implements StructuredDataProvider
{
    public int $calls = 0;

    #[Override]
    public function nodes(PageContext $page): iterable
    {
        ++$this->calls;
        $id = $page->siteId('organization');

        return null === $id ? [] : [Node::of('Organization', ['telephone' => '+15145550100'], $id)];
    }
}

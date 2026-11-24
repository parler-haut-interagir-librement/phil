<?php

declare(strict_types=1);

namespace Phil\FeedBundle\Tests\Fixtures\Content;

use Phil\ContentBundle\Domain\Content\ContentRoot;
use Phil\ContentBundle\Domain\Dimension\DimensionKey;

final class NewsRoot implements ContentRoot
{
    public function createDimensionContent(DimensionKey $dimension): NewsRow
    {
        return new NewsRow($this, $dimension);
    }
}

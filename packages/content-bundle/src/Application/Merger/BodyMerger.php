<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Application\Merger;

use Override;
use Phil\ContentBundle\Application\Pipeline\Merger;
use Phil\ContentBundle\Domain\Capability\BodyCapable;
use Phil\ContentBundle\Domain\Content\DimensionContent;
use Phil\ContentBundle\Domain\ValueObject\Body;

/**
 * Merges `BodyCapable`: the source's body, whole, when it has one. The source, its format and its HTML
 * travel together, so the HTML always matches the source it was compiled from.
 */
final readonly class BodyMerger implements Merger
{
    #[Override]
    public function merge(DimensionContent $target, DimensionContent $source): void
    {
        if (!$target instanceof BodyCapable || !$source instanceof BodyCapable) {
            return;
        }

        $body = $source->getBody();

        if ($body instanceof Body) {
            $target->setBody($body);
        }
    }
}

<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Application\Merger;

use Override;
use Phil\ContentBundle\Application\Pipeline\Merger;
use Phil\ContentBundle\Domain\Capability\TemplateCapable;
use Phil\ContentBundle\Domain\Content\DimensionContent;

/**
 * Merges `TemplateCapable`: the source's template key when it sets one, and its field values over the
 * target's, key by key.
 *
 * The replacement is shallow. A field holding a list or a nested block is one value: the localized row
 * replaces it whole rather than mixing its items with the shared row's.
 */
final readonly class TemplateMerger implements Merger
{
    #[Override]
    public function merge(DimensionContent $target, DimensionContent $source): void
    {
        if (!$target instanceof TemplateCapable || !$source instanceof TemplateCapable) {
            return;
        }

        $templateKey = $source->getTemplateKey();

        if (null !== $templateKey) {
            $target->setTemplateKey($templateKey);
        }

        $target->setTemplateData(array_replace($target->getTemplateData(), $source->getTemplateData()));
    }
}

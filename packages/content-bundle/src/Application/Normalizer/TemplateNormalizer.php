<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Application\Normalizer;

use Override;
use Phil\ContentBundle\Application\Pipeline\Normalizer;
use Phil\ContentBundle\Domain\Capability\TemplateCapable;
use Phil\ContentBundle\Domain\Content\DimensionContent;

/**
 * `TemplateCapable` as plain data: `templateKey` and `templateData`, the field values as they are stored.
 */
final readonly class TemplateNormalizer implements Normalizer
{
    #[Override]
    public function normalize(DimensionContent $content): array
    {
        if (!$content instanceof TemplateCapable) {
            return [];
        }

        return [
            'templateKey' => $content->getTemplateKey(),
            'templateData' => $content->getTemplateData(),
        ];
    }
}

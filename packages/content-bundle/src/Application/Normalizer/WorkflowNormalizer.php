<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Application\Normalizer;

use DateTimeInterface;
use Override;
use Phil\ContentBundle\Application\Pipeline\Normalizer;
use Phil\ContentBundle\Domain\Capability\WorkflowCapable;
use Phil\ContentBundle\Domain\Content\DimensionContent;

/**
 * `WorkflowCapable` as plain data: `workflowPlace`, and `workflowPublished` as an RFC 3339 string with its
 * offset, so a reader never guesses the time zone.
 */
final readonly class WorkflowNormalizer implements Normalizer
{
    #[Override]
    public function normalize(DimensionContent $content): array
    {
        if (!$content instanceof WorkflowCapable) {
            return [];
        }

        return [
            'workflowPlace' => $content->getWorkflowPlace(),
            'workflowPublished' => $content->getWorkflowPublished()?->format(DateTimeInterface::RFC3339),
        ];
    }
}

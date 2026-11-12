<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Infrastructure\Scheduling;

use Override;
use Phil\Resource\ResourceReference;
use Phil\ScheduledTransitionsBundle\Infrastructure\Workflow\SubjectResolver;

/**
 * The `Publication` a scheduled transition names, for `phil/scheduled-transitions-bundle`'s workflow handler: it
 * applies the transition through the `phil_content_publication` workflow, which publishes or takes offline in a
 * transaction of its own, so nothing is left to save.
 */
final readonly class PublicationSubjects implements SubjectResolver
{
    public function __construct(private PublicationReferences $references)
    {
    }

    #[Override]
    public function resolve(ResourceReference $resource): ?object
    {
        return $this->references->publicationOf($resource);
    }

    #[Override]
    public function saved(object $subject): void
    {
        // The content is saved as it is published.
    }
}

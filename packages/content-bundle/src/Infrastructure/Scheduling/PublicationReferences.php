<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Infrastructure\Scheduling;

use Phil\ContentBundle\Application\Publication\Publication;
use Phil\ContentBundle\Domain\ValueObject\ContentReference;
use Phil\ContentBundle\Infrastructure\Doctrine\ContentRoots;
use Phil\DddBundle\Domain\Exception\DomainError;
use Phil\Resource\ResourceReference;

/**
 * How a content in one locale is named to `phil/scheduled-transitions-bundle`: its kind as the resource key, its
 * identifier and its locale after an `@` as the resource identifier — `news:42@fr`. An identifier holds no `@`, so the
 * name reads back without doubt.
 */
final readonly class PublicationReferences
{
    /**
     * @param list<string> $resourceKeys the kinds of content of `phil_content.roots`
     */
    public function __construct(private array $resourceKeys)
    {
    }

    public static function fromRoots(ContentRoots $roots): self
    {
        return new self($roots->resourceKeys());
    }

    public function referenceOf(Publication $publication): ResourceReference
    {
        return ResourceReference::of($publication->reference->resourceKey, $publication->reference->resourceId.'@'.$publication->locale->value);
    }

    /**
     * The publication a resource names, or `null` when it is not a content in one locale.
     */
    public function publicationOf(ResourceReference $resource): ?Publication
    {
        if (!\in_array($resource->key, $this->resourceKeys, true)) {
            return null;
        }

        $parts = explode('@', $resource->id);

        if (2 !== \count($parts)) {
            return null;
        }

        try {
            return Publication::of(ContentReference::of($resource->key, $parts[0]), $parts[1]);
        } catch (DomainError) {
            return null;
        }
    }
}

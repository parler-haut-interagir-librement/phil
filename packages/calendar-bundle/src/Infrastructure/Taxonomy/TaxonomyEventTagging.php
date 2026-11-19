<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Infrastructure\Taxonomy;

use Override;
use Phil\CalendarBundle\Application\EventTagging;
use Phil\CalendarBundle\Domain\Model\Event;
use Phil\Resource\ResourceReference;
use Phil\TaxonomyBundle\Application\Taxonomy;
use Phil\TaxonomyBundle\Domain\Exception\InvalidTaxonomy;
use Phil\TaxonomyBundle\Domain\Model\Tag;

/**
 * The tags of `phil/taxonomy-bundle`: an event is the resource `phil_calendar_event`, by its identifier. A name the
 * taxonomy cannot make a slug of — punctuation alone — is left out.
 */
final readonly class TaxonomyEventTagging implements EventTagging
{
    public const string RESOURCE = 'phil_calendar_event';

    /** The taxonomy's service. */
    public const string TAXONOMY = Taxonomy::class;

    public function __construct(
        private Taxonomy $taxonomy,
    ) {
    }

    #[Override]
    public function tag(Event $event, array $names): array
    {
        $kept = [];

        foreach ($names as $name) {
            try {
                $kept[] = $this->taxonomy->tag($name)->name();
            } catch (InvalidTaxonomy) {
                continue;
            }
        }

        return $this->namesOf($this->taxonomy->setTags($this->resource($event), $kept));
    }

    #[Override]
    public function tagsOf(Event $event): array
    {
        return $this->namesOf($this->taxonomy->tagsOf($this->resource($event)));
    }

    #[Override]
    public function forget(Event $event): void
    {
        $this->taxonomy->setTags($this->resource($event), []);
    }

    private function resource(Event $event): ResourceReference
    {
        return ResourceReference::of(self::RESOURCE, $event->id);
    }

    /**
     * @param list<Tag> $tags
     *
     * @return list<string>
     */
    private function namesOf(array $tags): array
    {
        return array_map(static fn (Tag $tag): string => $tag->name(), $tags);
    }
}

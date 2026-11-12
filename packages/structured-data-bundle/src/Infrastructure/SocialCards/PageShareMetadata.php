<?php

declare(strict_types=1);

namespace Phil\StructuredDataBundle\Infrastructure\SocialCards;

use Override;
use Phil\SocialCards\Exception\SocialCardsException;
use Phil\SocialCards\ShareImage;
use Phil\SocialCardsBundle\Application\Contract\ShareMetadataSource;
use Phil\StructuredData\Graph;
use Phil\StructuredData\LanguageString;
use Phil\StructuredData\Node;
use Phil\StructuredData\Reference;
use Phil\StructuredDataBundle\Application\PageStructuredData;

/**
 * The title, description and image a page is shared with, from its graph, so that one model feeds both. They are read
 * from what the page is about — the `mainEntity` of its `WebPage`, or the node that names a `mainEntityOfPage` —, then
 * from the `WebPage` itself. A page whose nodes say none of the three gives nothing.
 *
 * The graph is the one the page prints: its providers are not asked a second time. What the request sets through
 * phil/social-cards-bundle is read after this source and wins over it.
 */
final readonly class PageShareMetadata implements ShareMetadataSource
{
    public function __construct(
        private PageStructuredData $page,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function values(): array
    {
        $graph = $this->page->graph();
        $pageId = $this->page->context()->pageId('webpage');
        $webPage = null === $pageId ? null : $graph->get($pageId);
        $nodes = array_filter([$this->mainEntity($graph, $webPage), $webPage], static fn (?Node $node): bool => $node instanceof Node);

        return array_filter([
            'title' => $this->text($nodes, ['headline', 'name']),
            'description' => $this->text($nodes, ['description']),
            'image' => $this->image($graph, $nodes),
        ], static fn (string|ShareImage|null $value): bool => null !== $value);
    }

    private function mainEntity(Graph $graph, ?Node $webPage): ?Node
    {
        $entity = $this->first($webPage?->get('mainEntity'));

        if ($entity instanceof Reference || $entity instanceof Node) {
            return $graph->resolve($entity);
        }

        return array_find($graph->nodes(), static fn (Node $node): bool => $node->has('mainEntityOfPage'));
    }

    /**
     * The first text the nodes give under those properties, in the order of the nodes.
     *
     * @param array<Node>  $nodes
     * @param list<string> $properties
     */
    private function text(array $nodes, array $properties): ?string
    {
        foreach ($nodes as $node) {
            foreach ($properties as $property) {
                $text = $this->string($this->first($node->get($property)));

                if (null !== $text) {
                    return $text;
                }
            }
        }

        return null;
    }

    /**
     * @param array<Node> $nodes
     */
    private function image(Graph $graph, array $nodes): ?ShareImage
    {
        foreach ($nodes as $node) {
            foreach (['image', 'primaryImageOfPage'] as $property) {
                $image = $this->shareImage($graph, $this->first($node->get($property)));

                if ($image instanceof ShareImage) {
                    return $image;
                }
            }
        }

        return null;
    }

    /**
     * An address, or an `ImageObject` given or referred to; `null` for an address a shared page cannot name, so that a
     * value of the graph never breaks the tags of the page.
     */
    private function shareImage(Graph $graph, mixed $value): ?ShareImage
    {
        $image = $value instanceof Reference ? $graph->resolve($value) : $value;

        try {
            if (\is_string($image)) {
                return new ShareImage($image);
            }

            if (!$image instanceof Node) {
                return null;
            }

            $url = $this->string($image->get('contentUrl')) ?? $this->string($image->get('url'));

            return null === $url ? null : new ShareImage($url, $this->string($image->get('caption')), $this->integer($image->get('width')), $this->integer($image->get('height')));
        } catch (SocialCardsException) {
            return null;
        }
    }

    private function first(mixed $value): mixed
    {
        return \is_array($value) ? $value[0] ?? null : $value;
    }

    private function string(mixed $value): ?string
    {
        return match (true) {
            \is_string($value) => $value,
            $value instanceof LanguageString => $value->value,
            default => null,
        };
    }

    private function integer(mixed $value): ?int
    {
        return \is_int($value) ? $value : null;
    }
}

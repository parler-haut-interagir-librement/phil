# Installation

```sh
composer require phil/related-content
```

The package needs PHP 8.5 or later with the extension `mbstring`, symfony/clock 8.2 and symfony/service-contracts 3.6.
It ships no Symfony bundle: the application registers the services it uses.

## The item source

The library reads the published items through `Contract\ItemSource`. An item is an identifier, keywords by index, a
date and a language:

```php
use Phil\RelatedContent\Contract\ItemSource;
use Phil\RelatedContent\Item;

final readonly class ArticleSource implements ItemSource
{
    public function __construct(private ArticleRepository $articles)
    {
    }

    public function item(string $id): ?Item
    {
        $article = $this->articles->findPublished($id);

        return null === $article ? null : $this->toItem($article);
    }

    public function candidates(Item $item): iterable
    {
        // The published articles sharing a topic, an author or a series, the most recent first.
        foreach ($this->articles->sharingAnyOf($item->keywords, limit: 500) as $article) {
            yield $this->toItem($article);
        }
    }

    private function toItem(Article $article): Item
    {
        return new Item('article:'.$article->id, [
            'topics' => $article->topicSlugs(),
            'authors' => $article->authorIds(),
            'series' => null === $article->series ? [] : [$article->series->id],
        ], $article->publishedAt, $article->locale);
    }
}
```

`item()` returns null for anything a reader may not see: a draft, a retired page, a product out of the catalogue. The
engine then leaves it out of every list, pinned or not. With a taxonomy, the source passes its items through
`TaxonomyExpander` so that sibling terms count — see [Scoring](scoring.md#broader-terms).

## Computing at publication

`RelatedContent::published()` computes the neighbours of an item, keeps them, and computes again the items it touches;
`retired()` takes it out of every list. With phil/publishing, `Bridge\Publishing\PublishingListener` does both from
`PagePublished` and `PageRetired`, the owner identifier of the event being the item identifier:

```yaml
# config/services.yaml
services:
    Phil\RelatedContent\RelevanceRules:
        factory: ['Phil\RelatedContent\RelevanceRules', 'forContent']

    Phil\RelatedContent\Neighbourhood:
        arguments:
            $source: '@App\Related\ArticleSource'

    Phil\RelatedContent\RelatedContent:
        arguments:
            $source: '@App\Related\ArticleSource'
            $store: '@App\Related\DoctrineRelatedStore'

    Phil\RelatedContent\Bridge\Publishing\PublishingListener:
        tags:
            - { name: kernel.event_listener, event: Phil\Publishing\PagePublished, method: onPagePublished }
            - { name: kernel.event_listener, event: Phil\Publishing\PageRetired, method: onPageRetired }
```

Each call computes at most `RelevanceRules::$maxRecomputes` items and returns the others in
`Recomputation::$deferred`. An application with many items dispatches one Messenger message per deferred identifier —
the identifier only — and its handler calls `RelatedContent::compute($id)`.

## Showing the neighbours

A page reads what was kept, with `RelatedContent::related()`; it never computes. Here the service is a Twig global named
`related_content`:

```twig
{% set related = related_content.related('article:' ~ article.id) %}
{% if related and related|length > 0 %}
    <aside aria-labelledby="related-title">
        <h2 id="related-title">{{ 'Read also'|trans }}</h2>
        <ul>
            {% for neighbour in related.top(4) %}
                <li>{{ include('article/_card.html.twig', {id: neighbour.id}) }}</li>
            {% endfor %}
        </ul>
    </aside>
{% endif %}
```

`RelatedSet::byReason()` gives the neighbours of one reason, for a shop's « Accessories » or « Bought together »
blocks. `RelatedSet::toArray()` and `fromArray()` turn a set into an array a database column or a cache keeps.

## Keeping the neighbours

`Store\InMemoryRelatedStore` keeps them for the life of the process, which suits tests and small sites rebuilt at
start. An application implements `Contract\RelatedStore` over its database, with a reverse index for `pointingAt()`:
one row per item and neighbour is enough, and holds no personal data.

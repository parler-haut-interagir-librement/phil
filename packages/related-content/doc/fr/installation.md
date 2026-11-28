<!-- phil-dev source-sha256=5238cffe409fb7a03511afac3c65f84a12d35e0b5d5c23de23717d485f7b82b1 -->
# Installation

```sh
composer require phil/related-content
```

Le paquet demande PHP 8.5 ou plus récent avec l'extension `mbstring`, symfony/clock 8.2 et symfony/service-contracts
3.6. Il ne fournit aucun bundle Symfony : l'application enregistre les services qu'elle emploie.

## La source des éléments

La bibliothèque lit les éléments publiés par `Contract\ItemSource`. Un élément est un identifiant, des mots-clés par
indice, une date et une langue :

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

`item()` retourne nul pour tout ce qu'un lecteur ne peut pas voir : un brouillon, une page retirée, un produit sorti du
catalogue. Le moteur l'omet alors de toutes les listes, épinglé ou non. Avec une taxonomie, la source passe ses éléments
par `TaxonomyExpander` pour que les termes frères comptent — voir [Pointage](scoring.md#termes-plus-larges).

## Calculer à la publication

`RelatedContent::published()` calcule les voisins d'un élément, les conserve, et recalcule les éléments qu'il touche ;
`retired()` le retire de toutes les listes. Avec phil/publishing, `Bridge\Publishing\PublishingListener` fait les deux à
partir de `PagePublished` et `PageRetired`, l'identifiant du propriétaire de l'événement étant l'identifiant de
l'élément :

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

Chaque appel calcule au plus `RelevanceRules::$maxRecomputes` éléments et retourne les autres dans
`Recomputation::$deferred`. Une application qui compte beaucoup d'éléments envoie un message Messenger par identifiant
différé — l'identifiant seulement — et son *handler* appelle `RelatedContent::compute($id)`.

## Afficher les voisins

Une page lit ce qui a été conservé, avec `RelatedContent::related()` ; elle ne calcule jamais. Ici, le service est une
globale Twig nommée `related_content` :

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

`RelatedSet::byReason()` donne les voisins d'une seule raison, pour les blocs « Accessoires » ou « Achetés ensemble »
d'une boutique. `RelatedSet::toArray()` et `fromArray()` transforment un ensemble en un tableau qu'une colonne de base
de données ou un cache conserve.

## Conserver les voisins

`Store\InMemoryRelatedStore` les conserve pour la durée du processus, ce qui convient aux tests et aux petits sites
reconstruits au démarrage. Une application implémente `Contract\RelatedStore` au-dessus de sa base de données, avec un
index inverse pour `pointingAt()` : une ligne par élément et par voisin suffit, et ne contient aucun renseignement
personnel.

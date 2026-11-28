<!-- phil-dev source-sha256=d3045967ba727beeec3af9ebd4896e55628fb63eab75a13128e975836ecb703b -->
# Tests

## Les tests du paquet

Les tests sont des tests PHPUnit sous `tests/Unit`, lancés depuis la racine du monodépôt par `composer test`, ou seuls :

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

Ils s'exécutent sans noyau, sans base de données et sans réseau. Les ports sont implémentés en mémoire sous
`tests/Support` :

- `MemorySource` contient les éléments publiés, donne chacun comme candidat, et se souvient des éléments demandés ;
- `MemoryPins`, `MemoryRelations`, `MemorySimilar` et `MemoryTaxonomy` répondent à partir de ce qu'on leur donne ;

et l'horloge est le `MockClock` de Symfony. `RelatedContentTest` exécute toute la chaîne : la publication, un changement
de sujet qui déplace un élément d'une liste à l'autre, les éléments différés au-delà de la borne, et un retrait qui
oublie aussi les comptes de paires. Dans le monodépôt, Infection maintient le paquet à 100 % des mutants couverts.

## Le banc d'essai

`tests/Benchmark/NeighbourhoodBench` calcule les voisins d'un article contre 500 candidats — sujets avec termes plus
larges, auteurs, séries, dates — et les comptes de paires d'un produit très consulté, avec
`RelevanceRules::forContent()` :

```sh
composer bench -- packages/related-content/tests/Benchmark
```

## Tester la source d'une application

Une application teste sa propre `ItemSource` en calculant un ensemble avec le magasin en mémoire :

```php
use Phil\RelatedContent\Neighbourhood;
use Phil\RelatedContent\RelatedContent;
use Phil\RelatedContent\RelevanceRules;
use Phil\RelatedContent\Store\InMemoryRelatedStore;

$store = new InMemoryRelatedStore();
$related = new RelatedContent($source, new Neighbourhood($source, RelevanceRules::forContent()), $store);
$related->published('article:42');

self::assertSame(['article:17', 'article:8'], $store->find('article:42')?->ids());
```

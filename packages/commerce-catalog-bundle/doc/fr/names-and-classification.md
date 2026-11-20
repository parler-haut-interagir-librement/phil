<!-- phil-dev source-sha256=4a3abb0d7b38eeec074fb8f9525dc779f486bfcd905e390a94a99adff17a544a -->
# Noms et classement

## Noms traduits

Le nom d'un produit est écrit dans son propre texte ; ses traductions vivent sur `ProductTranslation`, une rangée par
produit et par locale, grâce aux traductions de `phil/doctrine-behaviours`. Avec les locales déclarées dans la
[configuration](configuration.md), `ProductTexts` les lit.

```php
use Phil\CommerceCatalogBundle\Application\ProductTexts;

$texts->translate($dice, 'fr')->rename('Jeu de dés');   // the stored row, or a new empty one, persisted for the next flush
$names = $texts->of($products);                          // one query for the list, in the request's locale
$names->name($dice);                                     // "Jeu de dés", or the product's own name
$texts->locales($dice);                                  // ['fr'], for the alternates of its page
```

Une traduction est servie en entier, depuis la première locale de la chaîne de repli qui en a une ; un champ n'est
jamais copié d'une autre locale, et une traduction dont le nom n'est pas encore écrit retombe sur le nom propre du
produit. Une ligne de commande prend sa description dans la locale de la requête, par `ProductSellables`.

| Colonne de `phil_commerce_catalog_product_translation` | Type | Contient |
|---|---|---|
| `id` | `INTEGER`, clé primaire | La rangée |
| `product`, `locale` | `VARCHAR(64)` avec une clé étrangère, `VARCHAR(12)` ; uniques ensemble | Le produit, supprimé avec lui, et la locale |
| `name` | `VARCHAR(255)` | Le nom dans cette locale ; vide tant qu'il n'est pas écrit |

## Catégories et étiquettes

Avec `phil/taxonomy-bundle` installé et sa taxonomie enregistrée, `ProductClassification` classe les produits dans son
arbre de catégories et ses étiquettes, partagés avec le reste du site, renommés et fusionnés à un seul endroit. Un
produit est la ressource `phil_commerce_product`, désignée par son *SKU*.

```php
use Phil\CommerceCatalogBundle\Application\Contract\ProductClassification;

$classification->categorize($dice, ['games', 'dice']);   // by category keys; an unknown key is refused
$classification->tag($dice, ['Gift ideas', 'Résine']);   // the names of the tags it now has
$classification->inCategory('games');                    // SKUs filed in the category and its whole branch
$classification->forget($dice);                          // every tag and category taken away
```

Sans la taxonomie, le service et son alias sont retirés du conteneur.

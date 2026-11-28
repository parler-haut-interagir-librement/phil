<!-- phil-dev source-sha256=d08477b1c41aeaf2eb6b1aa225404ef202095c5b8eefc79c1daab00279abb3b0 -->
# phil/related-content

**Afficher des pages et des produits liés sans profiler les lecteurs : termes communs pondérés, épingles et accessoires
manuels, comptes d'achats groupés par paire, jamais par personne.**

phil/related-content est une bibliothèque PHP simple. Elle trouve les voisins d'un contenu ou d'un produit à sa
publication, les conserve, et laisse une page les lire sans rien calculer :

- **des indices pondérés** — les termes d'un vocabulaire, avec leurs termes plus larges à un poids moindre, les
  auteurs, les séries, les dates rapprochées — s'additionnent en un score ; sous le seuil, rien n'est proposé, de sorte
  qu'un élément sans vrai voisin n'affiche aucun bloc plutôt qu'un bloc faible ;
- **les épingles** : les voisins choisis par une personne de la rédaction viennent en premier, dans l'ordre qu'elle a
  fixé ;
- **des relations typées** pour les produits : accessoire, complément (affiché sur les deux produits), version
  supérieure ;
- **achetés ensemble** et **vus ensuite** : des comptes anonymes par paire de produits, ignorés sous un compte minimal,
  jamais une trace de qui a acheté ou vu quoi ;
- **le texte semblable**, par un moteur de recherche qui sait le trouver, au moyen d'un port.

Elle ne construit aucun profil de lecteur et ne fait aucun filtrage collaboratif : ce qu'une page affiche dépend de
l'élément, jamais de la personne qui le lit.

Les voisins sont calculés quand un élément est publié, modifié ou retiré. Les éléments qui affichaient un élément
modifié sont recalculés, tout comme ses nouveaux voisins, et un élément retiré quitte toutes les listes ; au-delà d'une
borne, le reste est retourné pour que l'application le mette en file d'attente, un identifiant par message.

## État

| Partie | État |
|---|---|
| `Neighbourhood`, `RelevanceRules`, `KeywordIndex`, `DateProximity`, `TaxonomyExpander` | Implémentés et testés |
| `RelatedContent` : calculer à la publication, conserver, recalculer les dépendants, retirer | Implémenté et testé |
| Épingles, relations typées, comptes de paires et `PairRecorder` | Implémentés et testés |
| `InMemoryRelatedStore` et `InMemoryPairCounts` | Implémentés et testés ; un magasin en base de données revient à l'application ou à un bundle |
| L'écouteur de phil/publishing, dans `Bridge\Publishing` | Implémenté et testé |
| Adaptateurs au-dessus d'un paquet de contenu, de catalogue, de taxonomie ou de recherche | Non fournis : l'application implémente les ports |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation, et le calcul des voisins à la publication des pages. |
| [Pointage](doc/fr/scoring.md) | Les indices, le seuil, l'ordre des voisins et les bornes. |
| [Produits](doc/fr/products.md) | Les relations typées, les achetés ensemble et les vus ensuite, et ce qui n'est jamais conservé. |
| [Points d'extension](doc/fr/extension-points.md) | Les ports, un indice personnalisé, et quand ils sont stables. |
| [Tests](doc/fr/testing.md) | Les tests du paquet et son banc d'essai. |
| [Référence](doc/fr/reference.md) | Chaque type public, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/related-content
```

```php
use Phil\RelatedContent\Neighbourhood;
use Phil\RelatedContent\RelatedContent;
use Phil\RelatedContent\RelevanceRules;
use Phil\RelatedContent\Store\InMemoryRelatedStore;

// $source implements Contract\ItemSource over the application's published contents.
$related = new RelatedContent($source, new Neighbourhood($source, RelevanceRules::forContent()), new InMemoryRelatedStore());

$related->published('article:42');               // at publication: computed and kept

foreach ($related->related('article:42')?->top(4) ?? [] as $neighbour) {
    echo $neighbour->id, ' ', $neighbour->score, "\n"; // on the page: read, never computed
}
```

```text
article:17 300
article:8 140
```

## Prérequis

PHP 8.5 ou plus récent, avec l'extension `mbstring` ; symfony/clock 8.2 et symfony/service-contracts 3.6.
phil/publishing est facultatif.

## Contribuer

Les contributions sont bienvenues dans le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).

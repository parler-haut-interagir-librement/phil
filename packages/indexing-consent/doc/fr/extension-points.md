<!-- phil-dev source-sha256=b921232b225b16b8719a7d2d1631f88d5dfb487bec2a50650328733201d2fa51 -->
# Points d'extension

La librairie est du PHP simple et ne déclare aucun service. Ce qu'une application écrit, c'est le contrat sur ses fiches
publiques ; ce qui les lit demande à la politique.

## `HasIndexingConsent`

`Phil\IndexingConsent\Contract\HasIndexingConsent` — une fiche publique dont la personne qu'elle concerne décide de la
portée.

| Méthode | Contrat |
|---|---|
| `indexing(): Indexing` | Le choix de la personne, tel que la fiche le garde. Peu coûteux et sans entrée-sortie : un lecteur le demande pour chaque fiche qu'il parcourt. |

Gardez le choix dans une colonne de la fiche, sa valeur `Indexing`, `listed` par défaut. Quand deux choix s'appliquent —
celui de la personne, et celui du site pour un genre de page —, répondez avec `Indexing::strictest()` des deux.

```php
#[ORM\Column(enumType: Indexing::class)]
private Indexing $indexing = Indexing::Listed;

public function indexing(): Indexing
{
    return Indexing::strictest($this->indexing, $this->directory->indexing());
}
```

## Le demander

Un lecteur ne lit jamais la colonne lui-même : il demande à `IndexingPolicy`, qui répond aussi pour les fiches qui
n'implémentent pas le contrat.

- **Le plan de site** garde les pages que donne `IndexingPolicy::filter($records, Reader::Sitemap)`.
- **Les données structurées** d'une page décrivent son sujet seulement quand `IndexingPolicy::allows($record,
  Reader::StructuredData)`.
- **La page** envoie `IndexingPolicy::of($record)->robots()`, quand il y en a un, en `<meta name="robots">` ou en
  `X-Robots-Tag`.
- **Les listes du site** — un répertoire, une recherche, une page de fiches — gardent ce que `Reader::SiteListings`
  permet.
- **Une requête** qui filtre sur la valeur conservée garde `Indexing::allowing($reader)`, plutôt que de charger chaque
  fiche pour la lui demander.
- **Une page qui montre plusieurs fiches** est aussi étroite que `IndexingPolicy::ofAll(...$records)`.

## La requête d'une page

Le contrôleur d'une page qui montre une fiche met l'`Indexing` de la fiche sur la requête, sous
`IndexingPolicy::REQUEST_ATTRIBUTE`. Les autres lecteurs de la page — ses données structurées, un écouteur qui écrit
les directives aux robots — lisent cet attribut plutôt que de connaître la fiche. Un lecteur qui y trouve une valeur
qu'il ne reconnaît pas la lit comme `Unlisted`, la plus étroite : une erreur ne remet jamais en vue une fiche retirée.

```php
$request->attributes->set(IndexingPolicy::REQUEST_ATTRIBUTE, $listing->indexing());
```

Un changement de choix atteint chaque lecteur à sa lecture suivante ; la cache d'une liste, d'un plan de site ou d'une
page est invalidée avec la fiche.

<!-- phil-dev source-sha256=e89488ebf5bad900406da170c045168e9fe8c0a5d2cb5e5b1158095fbde69d1d -->
# Points d'extension

La bibliothèque est en PHP simple et ne déclare aucun service. Ce qu'une application écrit, ce sont des fournisseurs ;
ce qu'elle peut remplacer, c'est le conteneur auquel ils ajoutent.

## `UrlProvider`

`Phil\Sitemap\UrlProvider` — ajoute les pages d'une partie d'un site.

| Méthode | Contrat |
|---|---|
| `provide(UrlContainer $urls, ?string $section): void` | Ajoute ses pages, chacune dans une section ; quand on lui demande une section, il peut n'ajouter que les pages de celle-ci et éviter de charger les autres — `null` les demande toutes |

## `UrlContainer`

`Phil\Sitemap\UrlContainer` — là où les fournisseurs ajoutent des pages. `Sitemap` est celui qui est fourni : il garde
les pages par section, seulement la section demandée quand il est construit pour une seule, et les coupe en fichiers.

| Méthode | Contrat |
|---|---|
| `add(Url $url, string $section): void` | Garde la page dans la section ; une section est vérifiée par `Sitemap::checkSection()` |

Un conteneur propre à l'application — un qui envoie les pages directement dans un fichier, par exemple — respecte les
règles des sections, pour que `XmlWriter` et les noms de fichiers restent valables. Voyez [Référence](reference.md).

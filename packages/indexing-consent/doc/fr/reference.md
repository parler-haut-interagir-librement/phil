<!-- phil-dev source-sha256=3acf8ed51a8e33f51bf76e2fd3c29b09032e134d47fce188fdabf0fdce454282 -->
# Référence

## `Indexing`

`Phil\IndexingConsent\Indexing` — une énumération adossée, conservée par sa valeur.

| Cas | Valeur | Sens |
|---|---|---|
| `Listed` | `listed` | Partout : moteurs de recherche, plan de site, données structurées, listes du site. |
| `NotIndexed` | `not_indexed` | Sur le site et dans ses listes, hors des moteurs de recherche, du plan de site et des données structurées. |
| `Unlisted` | `unlisted` | Joignable seulement par son adresse : hors des listes et de la recherche du site aussi. |

Ce que `allows(Reader $reader): bool` répond à chaque lecteur :

| | `SearchEngines` | `Sitemap` | `StructuredData` | `SiteListings` |
|---|---|---|---|---|
| `Listed` | oui | oui | oui | oui |
| `NotIndexed` | non | non | non | oui |
| `Unlisted` | non | non | non | non |

| Méthode | Retourne |
|---|---|
| `robotsDirectives(): list<string>` | `['noindex', 'noarchive']` pour une fiche qui n'est pas listée, `[]` pour une qui l'est. |
| `robots(): ?string` | La même chose, jointe comme la prennent `<meta name="robots" content="…">` et `X-Robots-Tag` : `noindex, noarchive`, ou `null`. |
| `isStricterThan(Indexing $other): bool` | Si cette valeur garde la fiche plus à l'étroit que l'autre : `Unlisted`, puis `NotIndexed`, puis `Listed`. |
| `strictest(Indexing $first, Indexing ...$others): Indexing` | La plus stricte des valeurs, pour une fiche dont le titulaire et la page décident chacun. |
| `allowing(Reader $reader): list<Indexing>` | Les valeurs que le lecteur permet, de la plus large : ce que garde une requête qui filtre sur la valeur conservée plutôt que de demander à chaque fiche. `[Listed]` pour les moteurs de recherche, le plan de site et les données structurées ; `[Listed, NotIndexed]` pour les listes du site. |

`noindex` garde la page hors des résultats ; `noarchive` demande aux moteurs de ne pas en garder de copie, pour qu'une
fiche retirée ne survive pas dans une cache ; Google n'en garde plus et l'ignore, d'autres moteurs le lisent. La page
elle-même reste joignable : un robot qui ne la récupère jamais ne peut pas lire ses directives ; ne l'interdisez donc
pas aussi dans `robots.txt`. Les directives gardent la page hors de la prochaine exploration ; une copie qu'un moteur
détient déjà en sort à cette exploration, ou plus tôt par l'outil de retrait du moteur.

## `Reader`

`Phil\IndexingConsent\Reader` — ce qui lit une fiche au-delà de sa propre page.

| Cas | Valeur | Qui demande |
|---|---|---|
| `SearchEngines` | `search_engines` | La page, pour ses directives aux robots. |
| `Sitemap` | `sitemap` | Le plan de site, avant de lister la page. |
| `StructuredData` | `structured_data` | Les données structurées de la page, avant de décrire son sujet. |
| `SiteListings` | `site_listings` | Le répertoire, la recherche et les pages de fiches du site. |

## `HasIndexingConsent`

`Phil\IndexingConsent\Contract\HasIndexingConsent::indexing(): Indexing` — la réponse de la fiche. Voir
[Points d'extension](extension-points.md).

## `IndexingPolicy`

`Phil\IndexingConsent\IndexingPolicy` — statique, pour tout objet.

| Méthode | Retourne |
|---|---|
| `of(object $record): Indexing` | La réponse de la fiche, ou `Listed` pour un objet sans le contrat : personne ne l'a retiré. |
| `ofAll(object ...$records): Indexing` | La réponse la plus stricte des fiches que montre une page ; `Listed` pour aucune. |
| `allows(object $record, Reader $reader): bool` | Si le lecteur peut utiliser la fiche. |
| `filter(iterable $records, Reader $reader): iterable` | Les fiches que le lecteur peut utiliser, à la demande, dans leur ordre, leurs clés gardées. |

| Constante | Valeur | Sens |
|---|---|---|
| `REQUEST_ATTRIBUTE` | `_indexing` | L'attribut de la requête d'une page sous lequel la page garde l'`Indexing` de la fiche qu'elle montre, pour que chaque lecteur de la page — ses données structurées, ses directives aux robots — lise une seule réponse. |

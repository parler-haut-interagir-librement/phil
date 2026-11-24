<!-- phil-dev source-sha256=9e4f5448b19abb358bec6cd9a2ef31069cbec2ffc6b6f4ddacc68684a8a5f7b9 -->
# Sources

Un flux est un service qui implémente `Phil\FeedBundle\Application\Contract\FeedSource`. L'autoconfiguration lui
donne l'étiquette `phil_feed.source` ; il est alors servi, lié et annoncé.

```php
interface FeedSource
{
    public function name(): string;
    public function channel(): Channel;
    public function items(FeedQuery $query): FeedPage;
    public function includes(PageEvent $page): bool;
}
```

## Le nom

De 1 à 64 lettres minuscules, chiffres, traits d'union ou soulignés, commençant par une lettre ou un chiffre, unique
parmi les sources : `news`, `events_2026`, `news-fr`. C'est le `{name}` de `/feeds/{name}.{format}`. Un nom d'une autre
forme, ou pris par une autre source, arrête avec `InvalidSource` la première requête qui lit les flux.

## Le canal

`channel()` donne ce que le flux dit de lui-même, **sans lire ses entrées** : chaque page qui appelle
`phil_feed_links()` le demande.

| Champ | Règle |
|---|---|
| `title` | De 1 à 255 caractères sur une ligne, sans espaces aux extrémités |
| `homePage` | Une `Url` : la page du site que le flux suit |
| `description` | Au plus 4096 octets ; `null` quand elle est vide |
| `language` | Une étiquette BCP 47, `fr` ou `fr-CA`, d'au plus 35 caractères |
| `authors` | Au plus 32 `Author` |
| `icon` | Une `Url` |

## Les entrées

`items()` donne au plus `$query->limit` entrées, les plus récentes d'abord, pour la page `$query->page` ;
`$query->offset()` vaut `(page - 1) × limit`. Elle répond par une `FeedPage` : les entrées, et si une page plus ancienne
suit. Une source qui lit une entrée de plus que la limite peut la donner au lieu de le dire : le bundle garde la limite
et lit l'entrée en trop comme une page plus ancienne. Une source qui ne garde aucune page plus ancienne répond la page 1
et une liste vide après elle ; une page au-delà de la dernière donne un `404`.

Une `FeedItem` est vérifiée à sa construction, pour qu'aucun format n'écrive ce qu'un autre refuserait :

| Champ | Règle |
|---|---|
| `id` | Une IRI absolue d'au plus 2048 octets : `tag:example.org,2026:news-42`, `urn:uuid:…`, ou l'adresse quand l'entrée ne bouge jamais. Elle nomme l'entrée pour de bon : un lecteur qui la revoit sait qu'il l'a déjà |
| `title` | De 1 à 1024 caractères sur une ligne, sans espaces aux extrémités |
| `url` | Une `Url` : la page que les lecteurs ouvrent |
| `published` | Quand elle est passée en ligne la première fois |
| `updated` | Quand elle a changé la dernière fois ; `null`, ou une date avant `published`, est lu comme `published` |
| `summary` | Du texte brut, d'au plus 64 Kio ; `null` quand il est vide |
| `contentHtml` | L'entrée entière en HTML, d'au plus 1 Mio ; les écrivains l'échappent comme chaque format le demande. Le bundle ne le purifie pas : c'est le HTML que le site montre sur la page de l'entrée |
| `authors` | Au plus 32 `Author` : un nom, et peut-être une page et une image |
| `categories` | Au plus 64 `Category` : un terme, et peut-être une étiquette |
| `attachment` | Une `Attachment` : une `Url`, un type de média sans paramètres, et peut-être une taille en octets, un titre et une durée en secondes — l'épisode d'un balado |
| `language` | L'étiquette BCP 47 propre à l'entrée, quand ce n'est pas celle du flux |

Chaque texte est en UTF-8 : un texte qui ne l'est pas, ou qui enfreint sa règle, lève `InvalidFeed`, dont le message
cite au plus 40 caractères de la valeur. Un flux contient au plus 500 entrées.

## Les pages de `phil/publishing`

`includes()` dit si une page passée en ligne ou hors ligne est de celles que le flux liste, ou listait : ses
concentrateurs sont alors avertis que le flux a changé. Elle lit les champs de l'événement — `ownerId`, `locale`,
`canonicalUrl`, `indexable` — et ne demande jamais plus à une base de données quand elle peut l'éviter, puisqu'elle
s'exécute pour chaque flux à chaque publication. Une source qui ne peut pas le dire répond `false` ; son flux n'est
jamais annoncé.

## Flux de contenus

`phil_feed.content` transforme les contenus publiés d'un type de `phil/content-bundle` en flux, sans classe à écrire :

```yaml
phil_feed:
    content:
        news-fr: { kind: news, locale: fr, title: 'Nouvelles', home_page: 'https://example.org/fr/nouvelles', tag_authority: 'example.org,2026' }
```

Le flux liste les contenus du type que la locale publie elle-même, avec un slug, le plus récemment publié d'abord,
moins ceux dont le SEO dit `noindex`. Chaque entrée a le titre et le résumé que montre la page du contenu — celui de
l'extrait, sinon celui du SEO —, son corps HTML, son adresse absolue et la date de sa dernière publication ; un contenu
sans adresse, ou jamais publié, est écarté. Son identifiant est `tag:{tag_authority}:{reference}` avec une autorité
d'étiquette, sinon son adresse. Une page appartient au flux quand son identifiant de propriétaire est une référence du
type et que sa langue est celle de la locale. Une page écartée pour `noindex` compte encore sur sa page : une page du
flux peut alors contenir moins d'entrées que la limite.

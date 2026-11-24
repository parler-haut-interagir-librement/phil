<!-- phil-dev source-sha256=e85b9ce34e97856c0ca5c58b2a8053cd86c824faead038e431ee79a6d9b1daae -->
# Points d'extension

Ce qu'une application implémente, configure ou utilise.

## Sources

Un service qui implémente `Phil\FeedBundle\Application\Contract\FeedSource` reçoit l'étiquette `phil_feed.source` par
autoconfiguration ; un bundle qui enregistre ses sources à la main ajoute l'étiquette. Chacune est un flux : voir
[Sources](sources.md). Une source est un service partagé par toutes les requêtes : elle ne garde rien d'une requête
pour la suivante.

## Écrivains

Un service qui implémente `Phil\FeedBundle\Application\Contract\FeedWriter`, avec l'étiquette `phil_feed.writer`,
écrit un format. Une application qui écrit un format à sa façon — pour ajouter les éléments d'une extension, comme les
éléments iTunes d'un balado — décore l'écrivain du bundle pour ce format
(`decorates: Phil\FeedBundle\Application\Writer\AtomWriter`) et peut lui remettre l'écrivain interne. Les formats sont
ceux de `FeedFormat` ; un nouveau format est un changement du bundle.

## Adresses

`Phil\FeedBundle\Application\Contract\FeedUrls` écrit l'adresse d'un flux. Celui du bundle, `RouterFeedUrls`, utilise la
route `phil_feed` ; une application qui sert ses flux depuis un autre hôte remplace l'alias.

## Configuration

| Option | Ce qu'elle étend |
|---|---|
| `limit` | Les entrées d'une page |
| `formats` | Les formats servis et liés |
| `cache` | La durée pendant laquelle un flux peut être mis en cache |
| `websub` | Les concentrateurs, leurs délais et le `User-Agent` |
| `content` | Les flux de contenus |

## Événements et messages

| Type | Rôle |
|---|---|
| `Phil\Publishing\PagePublished`, `PageRetired` | Écoutés quand un concentrateur est configuré |
| `Phil\FeedBundle\Infrastructure\WebSub\AnnounceFeedUpdate` | Acheminé par l'application vers un transport asynchrone |

## Stabilité

Le bundle est en alpha : `FeedSource`, `FeedWriter`, `FeedUrls`, les objets-valeurs, la route, la fonction Twig, la
configuration et le message peuvent changer dans une version mineure, comme le dit son CHANGELOG. Les classes marquées
`@internal` — `Text`, `XmlText`, `ContentEntries`, `PublishedContentEntries` — ne sont pas des points d'extension.

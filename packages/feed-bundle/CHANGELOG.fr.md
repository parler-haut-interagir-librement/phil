<!-- phil-dev source-sha256=8d565d3eb4e3e3a374b52b110788d4cbf34cdeffcb624defb33183c16e19eb9c -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/feed-bundle` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.0-alpha - 2026-11-24

### Ajouté

- `FeedSource`, le port d'un fil : un nom, un canal lu sans les entrées, les entrées d'une page — au plus la limite, les
  plus récentes d'abord, une de plus signifiant une page plus ancienne — et les pages de `phil/publishing` qu'il liste.
- Les objets-valeurs d'un fil — `Channel`, `FeedItem`, `Author`, `Category`, `Attachment`, `FeedQuery`, `FeedPage`,
  `Feed`, `FeedLinks` — vérifiés à leur construction : UTF-8, textes bornés, un IRI absolu comme identifiant, langues
  BCP 47, 500 entrées au plus ; `InvalidFeed` sinon.
- Des rédacteurs Atom (RFC 4287), RSS 2.0 et JSON Feed 1.1 à partir du même fil, le XML par `XMLWriter` : le contenu
  HTML échappé, les dates en UTC — RFC 3339, RFC 822 pour RSS —, les pièces jointes, les catégories, les auteurs, les
  liens self et hub, les pages de la RFC 5005, et les caractères que le XML ne peut pas contenir retirés.
- `/feeds/{name}.{format}` et `?page=` : le type de média de chaque format, `X-Content-Type-Options: nosniff`, un `ETag`
  tiré de l'empreinte du fil, le dernier changement comme `Last-Modified`, une `304` avant que le fil soit écrit, les
  en-têtes de cache fixés par `phil_feed.cache`, et une `404` pour un fil, un format ou une page inconnus.
- `phil_feed_links()`, les `<link rel="alternate">` des fils d'une page, par nom et par format.
- Les annonces WebSub : sur `PagePublished` et `PageRetired`, un `AnnounceFeedUpdate` qui nomme le fil seul ; son
  handler avise chaque hub configuré par `phil/safe-http`, refuse pour de bon un hub privé et rend les autres échecs à
  Messenger. Désactivées sans hub.
- Les fils de contenus de `phil/content-bundle` sous `phil_feed.content` : les contenus publiés et indexables d'une
  sorte dans une locale, nommés par leur adresse ou par une URI `tag:`.
- La configuration `phil_feed` : `limit`, `formats`, `allow_localhost`, `cache`, `websub`, `content`.
- Une recette Flex dans `recipe/0.1`, une cible de fuzzing des rédacteurs, et la documentation dans `doc/`, un fichier
  par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.

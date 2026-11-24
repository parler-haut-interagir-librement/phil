<!-- phil-dev source-sha256=fe497a71d8e0068e86930d5c68385b870d1dab9f25d791956107cd23557ab55e -->
# Référence

| Type | Genre | Rôle |
|---|---|---|
| `Phil\FeedBundle\PhilFeedBundle` | `AbstractBundle` | La configuration `phil_feed` et les services |
| `…\Application\Contract\FeedSource` | interface | Un flux : `name()`, `channel()`, `items()`, `includes()` |
| `…\Application\Contract\FeedWriter` | interface | Écrit un flux dans un format : `format()`, `write($feed, $links)` |
| `…\Application\Contract\FeedUrls` | interface | L'adresse absolue d'un flux dans un format et une page |
| `…\Application\Feeds` | `final class` | Les sources par nom : `names()`, `source()`, `feed($name, $page)`, `including($page)` |
| `…\Application\FeedWriters` | `final readonly class` | Les écrivains des formats servis : `for($format)`, `formats()` |
| `…\Application\FeedLinkBuilder` | `final readonly class` | Les liens self, hub, first, previous et next d'un flux écrit |
| `…\Application\Writer\AtomWriter` | `FeedWriter` | Atom, RFC 4287 |
| `…\Application\Writer\RssWriter` | `FeedWriter` | RSS 2.0 |
| `…\Application\Writer\JsonFeedWriter` | `FeedWriter` | JSON Feed 1.1 |
| `…\Application\Exception\UnknownFeed` | `RuntimeException` | Aucune source n'a ce nom |
| `…\Application\Exception\InvalidSource` | `LogicException` | Un nom qu'aucune adresse ne tient, ou deux sources du même nom |
| `…\Domain\FeedFormat` | enum | `Atom`, `Rss`, `Json` : `mediaType()`, `contentType()`, `label()` |
| `…\Domain\ValueObject\Channel` | objet-valeur | Ce qu'un flux dit de lui-même |
| `…\Domain\ValueObject\FeedItem` | objet-valeur | Une entrée |
| `…\Domain\ValueObject\Author` | objet-valeur | Un nom, une page, une image |
| `…\Domain\ValueObject\Category` | objet-valeur | Un terme et une étiquette ; `display()` |
| `…\Domain\ValueObject\Attachment` | objet-valeur | Un fichier d'une entrée : adresse, type de média, taille, titre, durée |
| `…\Domain\ValueObject\FeedQuery` | objet-valeur | La limite et la page demandées à une source ; `offset()` |
| `…\Domain\ValueObject\FeedPage` | objet-valeur | Les entrées que donne une source, et si une page plus ancienne suit |
| `…\Domain\ValueObject\Feed` | objet-valeur | Un canal, une page d'entrées, `updated`, `isEmpty()`, `fingerprint()` |
| `…\Domain\ValueObject\FeedLinks` | objet-valeur | Les adresses que nomme un flux écrit ; `feedId()` |
| `…\Domain\Exception\InvalidFeed` | `InvalidArgumentException` | Une partie qu'aucun format ne pourrait écrire |
| `…\Infrastructure\Http\FeedController` | contrôleur | `/feeds/{name}.{format}` avec ses en-têtes de cache |
| `…\Infrastructure\Routing\RouterFeedUrls` | `FeedUrls` | Les adresses par la route `phil_feed` |
| `…\Infrastructure\Twig\FeedExtension`, `FeedRuntime` | Twig | `phil_feed_links(names, formats)` |
| `…\Infrastructure\WebSub\AnnounceChangedFeeds` | écouteur | `PagePublished` et `PageRetired` vers `AnnounceFeedUpdate` |
| `…\Infrastructure\WebSub\AnnounceFeedUpdate` | message | Le nom d'un flux |
| `…\Infrastructure\WebSub\AnnounceFeedUpdateHandler` | handler | Avertit les concentrateurs |
| `…\Infrastructure\WebSub\Exception\HubNotificationFailed` | `RuntimeException` | Un concentrateur n'a pas pris l'annonce ; Messenger réessaie |
| `…\Infrastructure\Content\ContentFeedSource` | `FeedSource` | Un flux d'un type de `phil/content-bundle` dans une locale |
| `…\Infrastructure\DependencyInjection\FeedServices` | `final class` | Enregistre les services et les étiquettes |

## Services

| Id | Quand |
|---|---|
| `Phil\FeedBundle\Application\Feeds` | Toujours ; public |
| `Phil\FeedBundle\Application\FeedWriters`, `FeedLinkBuilder` | Toujours |
| `Phil\FeedBundle\Application\Writer\AtomWriter`, `RssWriter`, `JsonFeedWriter` | Pour chaque format de `formats`, avec l'étiquette `phil_feed.writer` |
| `Phil\FeedBundle\Infrastructure\Routing\RouterFeedUrls`, avec l'alias `FeedUrls` | Toujours |
| `Phil\FeedBundle\Infrastructure\Http\FeedController` | Toujours ; public |
| `Phil\FeedBundle\Infrastructure\Twig\FeedExtension`, `FeedRuntime` | Toujours, avec les étiquettes `twig.extension` et `twig.runtime` |
| `phil_feed.websub_client` | Avec un concentrateur : un `SafeHttpClient`, réinitialisé entre les messages |
| `Phil\FeedBundle\Infrastructure\WebSub\AnnounceChangedFeeds`, `AnnounceFeedUpdateHandler` | Avec un concentrateur |
| `phil_feed.content.{name}` | Pour chaque flux de contenus |

## Constantes

| Constante | Valeur |
|---|---|
| `FeedServices::SOURCE_TAG` | `phil_feed.source` |
| `FeedServices::WRITER_TAG` | `phil_feed.writer` |
| `FeedServices::WEBSUB_CLIENT` | `phil_feed.websub_client` |
| `RouterFeedUrls::ROUTE` | `phil_feed` |
| `Feeds::NAME_PATTERN` | `[a-z0-9][a-z0-9_-]{0,63}` |
| `FeedQuery::MAX_LIMIT`, `Feed::MAX_ITEMS` | `500` |
| `FeedQuery::MAX_PAGE` | `1000` |

## Les formats tels qu'écrits

| | Atom | RSS 2.0 | JSON Feed 1.1 |
|---|---|---|---|
| Type de média | `application/atom+xml` | `application/rss+xml` | `application/feed+json` |
| Dates | RFC 3339, UTC | RFC 822, UTC | RFC 3339, UTC |
| Identifiant du flux | Adresse de la première page | — | — |
| Auteurs du flux | `author`, sinon son titre quand il n'a aucune entrée ou qu'une entrée n'en a pas | — | `authors` |
| Titre d'une entrée | `title type="text"` | `title` | `title` |
| Résumé | `summary type="text"` | `description`, échappé en HTML | `summary` |
| Contenu | `content type="html"` | `content:encoded`, et `description` sans résumé | `content_html`, sinon `content_text` tiré du résumé ou du titre |
| Identifiant | `id` | `guid`, `isPermaLink="true"` quand c'est l'adresse | `id` |
| Auteurs | `author` avec `name`, `uri` | `dc:creator` | `authors` avec `name`, `url`, `avatar` |
| Catégories | `category term label` | `category` de l'étiquette, sinon du terme | `tags` |
| Pièce jointe | `link rel="enclosure"` | `enclosure`, longueur 0 quand elle est inconnue | `attachments` |
| Pages | `link rel="first"`, `previous`, `next` | `atom:link` des mêmes | `next_url` |

Les écrivains XML retirent les caractères que XML 1.0 ne peut pas contenir — un caractère de contrôle dans le HTML
d'une entrée —, qu'aucun analyseur ne lirait ; `XMLWriter` échappe le reste.

<!-- phil-dev source-sha256=8b634cc851045ec68b86f7b21b9ef2887d90cf7a1af3323faabaaa7945ed1100 -->
# Référence

Chaque type public du paquet, avec son rôle. Les noms sont relatifs à `Phil\SeoCheck`.

## La vérification

| Type | Genre | Rôle |
|---|---|---|
| `SeoChecker` | `final readonly class` | Exécute les règles sur une page : `check(CheckedPage $page, string $locale = 'en'): Report`. Les règles par défaut quand aucune n'est donnée ; une règle qui lève une exception devient l'avertissement `check.rule-failed`. |
| `CheckedPage` | `final readonly class` | La page telle qu'elle a été publiée — `canonicalUrl`, `locale`, `indexable`, `indexableByChannel` — et ses rendus ; `of()` lit chaque `RenderedPage`, `primary()`, `isIndexableIn(?string $channel)`. |
| `RenderedPage` | `final readonly class` | Un rendu tel que l'application le donne : adresse, HTML, statut, en-têtes en minuscules, canal ; `header(string $name)`. |
| `Rendering` | `final readonly class` | Un `RenderedPage` et le `PageMarkup` lu à partir de lui. |
| `PageCheckFactory` | `final readonly class` | `fromEvent(PagePublished $event): ?CheckedPage`, à partir de `RenderedPages` et, s'il est donné, de `ChannelIndexing`. |
| `Report` | `final readonly class`, `Countable` | Les constats dans l'ordre des règles : `findings(Severity $minimum)`, `withSeverity()`, `ofRule()`, `message()`, `translate()`, `inLocale()`, `isEmpty()`, `toArray()`. Pas de note globale. |
| `Finding` | `final readonly class` | Un point à corriger : règle, gravité, message, et le sujet dont il parle ; `toArray(string $message)`. |
| `Severity` | `enum` | `Error`, `Warning`, `Info` ; `isAtLeast(Severity $minimum)`. |
| `Message` | `final readonly class` | Une clé du domaine `phil_seo_check` et les valeurs de ses paramètres substituables. |

## Les ports

| Type | Genre | Rôle |
|---|---|---|
| `Contract\SeoRule` | interface | Une vérification : `name()`, `check(CheckedPage $page): iterable<Finding>`. |
| `Contract\RenderedPages` | interface | Le HTML d'une page sans requête : `renderingsOf(PagePublished $event)`, `renderingAt(Url $url)`. |
| `Contract\LinkStatuses` | interface | Le code de statut de chaque lien connu : `statuses(list<Url> $links): array<string, int>`. |
| `Contract\ChannelIndexing` | interface | Si une page est indexable dans un canal : `isIndexable(PagePublished $event, string $channel): ?bool`. |
| `Contract\ReportRenderer` | interface | `render(Report $report): string`. |

## Les règles

| Type | Nom | Rôle |
|---|---|---|
| `Rule\DefaultRules` | — | `create(?RenderedPages $pages = null, ?LinkStatuses $statuses = null): list<SeoRule>`, dans l'ordre du rapport. |
| `Rule\HttpStatus` | `http-status` | Chaque rendu répond par un succès ; la page à sa propre adresse ne redirige pas. |
| `Rule\PageSize` | `page-size` | La page n'est pas plus grande que ce que le lecteur lit. |
| `Rule\DocumentTitle` | `document-title` | Un seul titre, non vide, de `MIN_LENGTH` (10) à `MAX_LENGTH` (60) caractères. |
| `Rule\MetaDescription` | `meta-description` | Une seule méta-description, non vide, de `MIN_LENGTH` (50) à `MAX_LENGTH` (160) caractères. |
| `Rule\DocumentLanguage` | `document-language` | Le `lang` du document et `Content-Language` nomment la langue de publication. |
| `Rule\SingleH1` | `single-h1` | Un seul titre de premier niveau, avec du texte. |
| `Rule\Canonical` | `canonical` | Une seule adresse canonique absolue, celle de la publication, sur chaque chemin menant à la page. |
| `Rule\Indexing` | `indexing` | Les directives d'indexation concordent avec l'indicateur « indexable », par canal. |
| `Rule\Hreflang` | `hreflang` | Des traductions valides et réciproques, `MAX_PAGES` (50) lues au plus. |
| `Rule\CrawlableLinks` | `crawlable-links` | Des liens avec un `href`, pas `javascript:`. |
| `Rule\LinkText` | `link-text` | Des textes de lien qui disent où ils mènent. |
| `Rule\GenericLinkTexts` | — | `DEFAULTS`, les textes de lien génériques par langue principale, et `normalize()`. |
| `Rule\ImageAlt` | `image-alt` | Des images avec un `alt`, vide pour la décoration. |
| `Rule\BrokenLinks` | `broken-links` | Aucun lien mort, d'après `LinkStatuses`, `MAX_LINKS` (1 000) interrogés au plus. |
| `Bridge\StructuredData\StructuredDataRule` | `structured-data` | Le JSON-LD de la page, par le `Validator` de phil/structured-data. |
| `Bridge\SocialCards\ShareTagsRule` | `share-tags` | Les balises de partage de la page, par le `TagCheck` de phil/social-cards. |
| `Bridge\LinkChecker\LinkCheckerStatuses` | — | `LinkStatuses` par le `LinkChecker` de phil/link-checker ; `PAGE`, l'adresse où ses liens sont vérifiés. |

## Le balisage

| Type | Genre | Rôle |
|---|---|---|
| `Markup\MarkupReader` | `final readonly class` | Lit un `RenderedPage` avec `Dom\HTMLDocument` : `MAX_BYTES` (5 000 000), `MAX_ELEMENTS` de chaque sorte (5 000), `MAX_TEXT` caractères de chaque texte (1 000) ; le jeu de caractères de `Content-Type`, sinon celui de la page, sinon UTF-8. |
| `Markup\PageMarkup` | `final readonly class` | Ce qui a été lu : titres, `<meta>` par nom, langue, titres de premier niveau, liens canoniques et alternatifs, liens, images, et si la page a été coupée ; `meta(string $name)`. |
| `Markup\Link` | `final readonly class` | Un `<a>` : `href` tel qu'écrit ou null, texte. |
| `Markup\Alternate` | `final readonly class` | Un `<link rel="alternate" hreflang>` : `hreflang`, `href`. |
| `Markup\Image` | `final readonly class` | Un `<img>` : `src`, `alt`, et s'il est caché aux lecteurs. |
| `Markup\RobotsDirectives` | `final readonly class` | Lit les directives d'indexation d'un rendu à partir de `<meta>` et de `X-Robots-Tag` : `AGENTS`, `VALUED`, `MAX_DIRECTIVES` (200). |
| `Markup\RobotsDirective` | `final readonly class` | Une directive : nom, robot d'exploration ou null, source `meta` ou `header` ; `forbidsIndexing()`. |
| `Markup\Address` | `final class` | Une adresse lue dans une page, résolue par rapport à celle de la page et lue par phil/url : `resolve()`, `isAbsolute()`, `trim()`. |

## Les formats du rapport et les messages

| Type | Genre | Rôle |
|---|---|---|
| `Render\TextRenderer` | `final readonly class`, `ReportRenderer` | Une ligne par point, pour une console ou un journal. |
| `Render\JsonRenderer` | `final readonly class`, `ReportRenderer` | `Report::toArray()` en JSON, clés et paramètres substituables compris. |
| `Render\HtmlRenderer` | `final readonly class`, `ReportRenderer` | Une `<section>` avec une liste, les classes `isError`, `isWarning`, `isInfo`, chaque texte échappé, sans style ni script. |
| `Translation\Messages` | `final class` | `DOMAIN`, `resources()`, `translator()`, `translate()`, `locale()` : les messages pour symfony/translation, l'anglais étant la langue de repli. |
| `Translation\English` | `final class` | Les messages en anglais, par clé. |
| `Translation\French` | `final class` | Les messages en français, par clé. |

## Exceptions

| Type | Genre | Rôle |
|---|---|---|
| `Exception\SeoCheckException` | interface | Ce que chaque exception du paquet implémente. |
| `Exception\InvalidPage` | `final class` | Une page sans rendu, un statut hors de 100–599, ou un nom d'en-tête qui n'est pas un jeton. |

<!-- phil-dev source-sha256=34e056c75f6577f050d6379ea884eb449c4cf33fda147ec3500a17b67eeb2dcc -->
# Référence

## Pages

| Type | Genre | Rôle |
|---|---|---|
| `Url` | `final readonly class` | Une page : `loc`, `lastmod`, `changefreq`, `priority`, `alternates`, `images` |
| `ChangeFrequency` | enum | `always`, `hourly`, `daily`, `weekly`, `monthly`, `yearly`, `never` |
| `Alternate` | `final readonly class` | La même page dans une autre langue : `hreflang` et `href` |
| `Image` | `final readonly class` | Une image que la page montre, par son adresse |
| `InvalidSitemap` | `InvalidArgumentException` | Ce qu'un plan de site ne peut pas contenir |

| Règle | |
|---|---|
| Une adresse — page, variante, image, fichier | absolue, HTTP ou HTTPS, au plus 2048 caractères |
| Une priorité | de 0,0 à 1,0, par pas de 0,1, écrite avec une décimale |
| Les images d'une page | au plus 1000 |
| Un `hreflang` | une langue, peut-être suivie d'une écriture ou d'une région — `fr`, `fr-CA`, `zh-Hant-TW` — ou `x-default` ; `fr_CA` s'écrit `fr-CA` |

Les moteurs de recherche s'attendent à ce que les variantes d'une page listent toutes les langues de la page, la
sienne comprise.

## Sections et fichiers

| Type | Genre | Rôle |
|---|---|---|
| `UrlContainer` | interface | `add(Url, string $section)` |
| `UrlProvider` | interface | `provide(UrlContainer, ?string $section)` : ajoute les pages d'une partie d'un site |
| `Sitemap` | `final class`, un `UrlContainer` | Les pages par section ; `sections()`, `files()`, `checkSection()`, `sectionNamed()` et `sectionOfFile()` |

Une section compte de 1 à 64 lettres minuscules, chiffres, traits d'union ou soulignés. Elle commence par une lettre
ou un chiffre, et ne se termine pas par un souligné suivi de chiffres (`news_1`), qui nomment les parties numérotées
d'une section. Un `Sitemap` construit pour une section ne garde que les pages de celle-ci ; un fournisseur à qui on
demande une section peut éviter les autres pour ne pas les charger.

`files($perFile)` donne son nom à chaque fichier : celui de la section — `news` — quand ses pages tiennent dans un
fichier, des parties numérotées — `news_0`, `news_1` — sinon. Un fichier contient au plus 50 000 pages, comme le dit
le protocole. `Sitemap::sectionOfFile($name)` retrouve la section à partir du nom d'un fichier : `news` pour `news` et
pour `news_1`. `Sitemap::sectionNamed($key)` donne à un fournisseur la section d'une clé à lui qui peut se terminer
par un souligné et des chiffres — un type de contenu, un genre — : ce souligné devient un trait d'union,
`news_2026` donne `news-2026`.

## XML

`XmlWriter::urlset($urls)` écrit un fichier : chaque page avec son `<loc>`, puis ce qu'elle sait de `<lastmod>`,
`<changefreq>` et `<priority>`, un `<xhtml:link rel="alternate">` par variante et un `<image:image>` par image.
`XmlWriter::index($files)` écrit le `sitemapindex` : l'adresse de chaque fichier et le `<lastmod>` le plus récent de
ses pages, quand l'une en a un. Le texte est échappé pour le XML.

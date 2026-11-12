<!-- phil-dev source-sha256=9be4ab89df1cab6eafcdbbabb6f996f0923a2de9cb315d9024dbf19b4fd6c8fa -->
# Balises de partage

## Les métadonnées d'une page

`ShareMetadata` contient ce qu'une page dit d'elle-même quand on la partage. Seuls le titre et l'adresse sont requis.

| Champ | Clé de `fromArray()` | Règle |
|---|---|---|
| `title` | `title` | Sur une ligne, coupé à 300 caractères ; un titre fait seulement de blancs est refusé |
| `url` | `url` | Une adresse HTTP ou HTTPS absolue sans identifiants de connexion, de 2 048 caractères au plus |
| `description` | `description` | Sur une ligne, coupée à 1 000 caractères |
| `image` | `image` | Une `ShareImage`, une adresse, ou un tableau de `url`, `alt`, `width`, `height` et `type` |
| `type` | `type` | Un `ShareType` ou sa valeur : `website` (par défaut), `article`, `profile`, `book`, `video.other` |
| `locale` | `locale` | Une langue, peut-être une écriture et une région : `fr`, `fr-CA` et `fr_ca` s'écrivent `fr_CA` |
| `alternateLocales` | `alternate_locales` | 50 au plus, écrites comme `locale`, sans doublons ni la locale propre à la page |
| `siteName` | `site_name` | Sur une ligne, coupé à 200 caractères |
| `author` | `author` | Sur une ligne, coupé à 200 caractères ; écrit pour un article seulement |
| `publishedTime`, `modifiedTime` | `published_time`, `modified_time` | Une date, ou une chaîne que lit PHP ; écrites pour un article seulement |
| `twitterSite`, `twitterCreator` | `twitter_site`, `twitter_creator` | Un compte sur X : de 1 à 15 lettres, chiffres ou soulignés, le `@` facultatif |
| `fediverseCreator` | `fediverse_creator` | Un compte sur le fédivers, `@user@example.social`, le premier `@` facultatif |

« Sur une ligne » veut dire que les blancs, les séparateurs de ligne et les caractères de contrôle deviennent des espaces
simples, que les caractères de formatage invisibles — les contrôles de la direction du texte, les espaces de largeur
nulle, le trait d'union conditionnel — sont retirés, sauf l'antiliant et l'anti-antiliant de largeur nulle, qui
forment un émoji ou un mot, qu'un octet invalide est remplacé, et qu'un texte coupé se termine par des points de suspension. Un texte venu
d'un rédacteur ne brise donc jamais une page, alors qu'une adresse, une locale ou un compte qui n'en est pas un lance
`InvalidShareMetadata` là où la page est construite.

`fromArray()` refuse une clé qu'il ne connaît pas, pour qu'une clé mal orthographiée échoue au lieu de disparaître ; une
valeur null est une valeur absente.

`ShareImage` vérifie son adresse comme celle de la page, coupe son texte de remplacement à 420 caractères (le plus long
que garde X), accepte une largeur et une hauteur de 1 à 10 000 pixels, et un type de média qui est celui d'une image,
comme `image/png`.

## Les balises écrites

`MetaTags::of()` retourne les balises dans cet ordre, chacune seulement quand sa valeur est présente :

| Balises | À partir de |
|---|---|
| `<link rel="canonical">`, `<meta name="description">` | L'adresse, la description |
| `og:title`, `og:type`, `og:url`, `og:description`, `og:site_name`, `og:locale`, `og:locale:alternate` | La page |
| `og:image`, `og:image:type`, `og:image:width`, `og:image:height`, `og:image:alt` | L'image |
| `article:published_time`, `article:modified_time`, `article:author` | Les dates d'un article, en ISO 8601, et son auteur |
| `twitter:card`, `twitter:title`, `twitter:description`, `twitter:image`, `twitter:image:alt`, `twitter:site`, `twitter:creator` | La page |
| `fediverse:creator` | Le compte crédité sur le fédivers, tel que le lit Mastodon |

La carte de X est `summary_large_image` quand la page a une image et `summary` sinon, à moins qu'un `TwitterCard` soit
passé. `MetaTags::html()` écrit les balises une par ligne ; chaque valeur est échappée pour un attribut entre guillemets
doubles, un octet invalide remplacé.

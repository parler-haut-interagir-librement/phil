<!-- phil-dev source-sha256=82ad544bc74d82eb5095ef60374b70b2c323ccff8fcb3d7a4a1aa1953e728194 -->
# Rendu

Le contrôleur produit le rendu du contenu en ligne par les gabarits Twig de l'application, et quatre fonctions Twig
lisent du contenu, des URL et des balises de référencement depuis n'importe quel gabarit. Les deux ont besoin de
DoctrineBundle et de TwigBundle dans le noyau.

## Choisir le gabarit

`Application\Rendering\TemplateResolver::resolve(?string $templateKey): string` renvoie :

- le gabarit déclaré pour la clé sous `phil_content.templates` ;
- `phil_content.default_template` pour une ligne sans clé, avec une clé que personne n'a déclarée, ou d'une classe de
  ligne qui n'est pas `TemplateCapable` ;
- une `LogicException` quand aucun des deux ne s'applique.

```yaml
phil_content:
    templates:
        news: content/news.html.twig
        event: content/event.html.twig
    default_template: content/page.html.twig
```

La clé de gabarit se trouve d'habitude sur la ligne non localisée, pour que chaque locale produise la même mise en
page ([capabilities.md](capabilities.md#templatecapable)).

## Ce que reçoit le gabarit

| Variable | Contient |
|---|---|
| `content` | la ligne en ligne fusionnée, normalisée : les clés de la forme normalisée (`excerptTitle`, `bodyHtml`, `seoTitle`, `templateData`, … ; [pipeline.md](pipeline.md#la-forme-normalisée)) |
| `root` | l'entité racine |
| `reference` | sa `ContentReference` ; s'affiche sous la forme `news:0192f3c4-…` |
| `locale` | la locale demandée, en chaîne |
| `resolved_locale` | la locale servie, en chaîne |
| `left_language` | si la chaîne a quitté la langue demandée, pour que l'application le compte ou l'annonce |

**`content.bodyHtml` est la seule valeur qu'un gabarit peut afficher sans échappement** (`|raw`) : elle est purifiée
de nouveau à chaque lecture ([capabilities.md](capabilities.md#bodycapable)). Toutes les autres valeurs s'affichent
avec l'échappement de Twig.

```twig
{# templates/content/news.html.twig #}
<title>{{ content.seoTitle ?? content.excerptTitle }}</title>
{{ content_seo(content) }}

<h1>{{ content.excerptTitle }}</h1>
{% if left_language %}<p class="notice">This page is not available in your language.</p>{% endif %}
<div class="body">{{ content.bodyHtml|raw }}</div>

<a href="{{ content_url(reference) }}">Permalink</a>
{% if content.link %}<a href="{{ content_link_url(content.link) }}">Read more</a>{% endif %}
```

## Les fonctions Twig

Déclarées par `Infrastructure\Twig\ContentExtension`, servies à la demande par `Infrastructure\Twig\ContentRuntime`,
que Twig construit seulement quand un gabarit appelle l'une d'elles.

| Fonction | Renvoie |
|---|---|
| `content(reference, locale = null)` | le contenu en ligne d'une racine le long de la chaîne de la locale, normalisé, ou `null` |
| `content_single(resource_key, locale = null)` | le contenu en ligne d'un genre unique (`phil_content.singles`), tel que `content()` le lit, ou `null` avant sa création ou sa publication ; un genre non déclaré unique lève `InvalidContent` |
| `content_archive(reference, locale = null)` | les publications d'une racine dans une locale, des valeurs `ArchivedVersion` (`version`, `publishedAt`), la plus récente d'abord, quand son genre est sous `phil_content.archives` ; une liste vide sinon |
| `content_url(reference, locale = null, absolute = false)` | l'URL de la route actuelle d'une racine dans une locale, ou `null` |
| `content_link_url(link, locale = null, absolute = false)` | l'URL d'un `link` normalisé : son `url`, ou l'URL de la racine qu'il nomme (dans sa `locale` fixée quand il en a une), ou `null` |
| `content_seo(content)` | les balises `<meta name="description">`, `<meta name="keywords">`, `<meta name="robots">` et `<link rel="canonical">` d'un contenu normalisé, sous forme de HTML sûr |
| `content_schema(content, type = 'WebPage', url = null)` | le contenu sous forme de données schema.org, construit par `ContentSchema` : un `WebPage`, ou le type donné (`Article`, `NewsArticle`, `BlogPosting`, avec un `headline`), son nom, sa description, `datePublished`, son image (une URL absolue seulement), ses mots-clés, son URL (sinon l'URL canonique) et `inLanguage`, la locale de la requête |

- **Une référence** est une `ContentReference`, sa forme en chaîne (`news:0192f3c4-…`) ou une entité racine.
- **La locale** est par défaut celle de la requête en cours ; sans requête, la première locale déclarée (`en` quand
  aucune n'est déclarée).
- **Une page n'échoue jamais sur ce qu'elle lit ou ce vers quoi elle pointe.** `content()`, `content_url()` et
  `content_link_url()` répondent `null` pour tout ce que le domaine refuse : rien de publié le long de la chaîne, une
  locale non servie ou qui ne s'écrit pas comme une `Locale` (`fr-CA`), une référence mal formée. L'URL d'un genre
  monté sur aucune route vaut aussi `null`.
- **Une référence vers un genre qu'aucune racine ne déclare échoue quand même** (`InvalidContentMapping`) : c'est une
  erreur du gabarit ou de la configuration, pas un état du contenu.
- **`content_seo()` échappe chaque valeur qu'il affiche** : les balises peuvent s'afficher brutes parce qu'elles sont
  construites ici, pas parce qu'on a fait confiance aux valeurs. La description est `seoDescription`, ou
  `excerptDescription` en son absence ; `robots` liste `noindex` et `nofollow` quand ils sont définis ; les mots-clés
  sont joints par une virgule et une espace. Les balises sans valeur sont omises. Il n'affiche aucun `<title>` : le
  gabarit écrit le sien.

```twig
{% set teaser = content('news:0192f3c4-7b1e-7c4d-9a1f-2b3c4d5e6f70') %}
{% if teaser %}
    <a href="{{ content_url('news:0192f3c4-7b1e-7c4d-9a1f-2b3c4d5e6f70') }}">{{ teaser.excerptTitle }}</a>
{% endif %}

<link rel="alternate" hreflang="en" href="{{ content_url(reference, 'en', true) }}">
```

## L'archive d'un contenu

`Infrastructure\Http\ArchiveController` sert une version datée d'un contenu dont le genre est sous
`phil_content.archives`, avec le gabarit propre du contenu. L'application le monte sur une route à elle, avec le genre
comme valeur par défaut de `resourceKey`, et un paramètre `version` ; le contenu est celui que nomme son `slug` dans la
locale de la requête, ou le contenu d'un genre unique quand la route n'a pas de slug :

```yaml
now_archive:
    path: /{_locale}/now/{version}
    controller: Phil\ContentBundle\Infrastructure\Http\ArchiveController
    defaults: { resourceKey: now_page }
    requirements: { version: '\d{1,9}' }
```

Le gabarit reçoit les variables de `ContentController` — `content`, `reference`, `locale`, `resolved_locale`, la locale
de la requête puisqu'une archive n'a pas de repli, et `left_language`, `false` — et deux de plus : `archived`, l'`ArchivedVersion`
montrée, et `archive`, chaque publication de la locale. Un genre sans archive, un contenu introuvable, une version dont
la locale n'a pas été publiée et une locale non servie répondent 404.

```twig
<h1>{{ content.excerptTitle }}</h1>
{% if archived is defined %}<p>As of {{ archived.publishedAt|date('F j, Y') }}</p>{% endif %}
<ol>
    {% for published in content_archive(reference) %}
        <li><a href="{{ path('now_archive', {version: published.version}) }}">{{ published.publishedAt|date('F Y') }}</a></li>
    {% endfor %}
</ol>
```

## URL et locales

Les URL sont générées par `ContentUrlGenerator` sur la route de génération du genre de la racine
([routing.md](routing.md#générer-des-url)) ; `_locale` est ajouté seulement quand la locale diffère de celle de la
requête.

## schema.org

```twig
<script type="application/ld+json">{{ content_schema(content, 'NewsArticle', content_url(reference, absolute: true))|json_encode(constant('JSON_UNESCAPED_SLASHES') b-or constant('JSON_UNESCAPED_UNICODE') b-or constant('JSON_HEX_TAG'))|raw }}</script>
```

`JSON_HEX_TAG` écrit `<` et `>` sous forme d'échappements : aucune valeur ne peut donc fermer l'élément script. Un
type qui n'est pas un nom de type schema.org — une majuscule, puis des lettres — lève `InvalidArgumentException`.

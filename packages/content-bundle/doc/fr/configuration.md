<!-- phil-dev source-sha256=218a42b06108f8e53b89ae082524b6858013795e39ac2ffd5c3b3c8ffabaac94 -->
# Référence de la configuration

La configuration du bundle se trouve sous `phil_content`. Chaque clé est facultative. Une configuration vide construit
un conteneur dans lequel aucune locale n'est servie, aucune racine n'est déclarée et aucune route ne sert de contenu.

## Exemple complet

```yaml
# config/packages/phil_content.yaml
phil_content:
    # The served locales, each with the locales tried after it, in order.
    # A fallback must itself be a served locale; a chain never repeats a locale.
    locales:
        fr_CA: [fr]          # a reader of fr_CA gets fr_CA, then fr
        fr: []               # a reader of fr gets fr only
        en: []

    # The content root classes; each names its row class.
    roots:
        - App\Content\News
        - App\Content\StaticPage

    # Service id of the entity manager the Doctrine adapters write through (the default).
    entity_manager: doctrine.orm.entity_manager

    # The Symfony routes that mount ContentController for each resource key.
    # The first route of a kind is the one its URLs are generated on, and takes a {slug} parameter.
    routes:
        news: news_show
        static_page: [page_show, privacy]

    # The Twig template of each template key.
    templates:
        news: content/news.html.twig

    # The template of a row whose template key is absent or not declared.
    default_template: content/page.html.twig

    # The trusted role whose holders write raw HTML; without it, raw HTML is shown and published as text.
    raw_html_role: ROLE_EDITOR

    # The kinds that have a single content: created once, found without an identifier.
    singles: [home_page, contact]

    # The kinds whose every publication stays readable, dated.
    archives: [now_page]

    # The index of phil/search-bundle the published contents are fed to (the default).
    search_index: content

    # The administration, linked from an EasyAdmin back office.
    admin:
        enabled: true
        role: ROLE_EDITOR
        layout: admin/content_layout.html.twig
        timezone: America/Toronto
```

## Les clés

### `locales`

| | |
|---|---|
| Type | table de locale vers liste de locales |
| Valeur par défaut | `{}` (aucune locale servie) |
| Branche | `Phil\LocaleDimensions\DeclaredLocales`, construit par `DeclaredLocales::fromArray()` |

Chaque clé est une locale servie ; sa valeur est la liste des locales essayées après elle, dans l'ordre. La locale
demandée est toujours essayée en premier : `fr_CA: [fr]` déclare donc la chaîne `fr_CA → fr`.

Contraintes, vérifiées à la construction du conteneur (une `InvalidLocaleDimension` est levée par la validation de la
configuration) :

- une locale a une seule graphie : deux ou trois lettres minuscules, suivies au besoin d'un trait de soulignement et
  de deux lettres majuscules (`fr`, `fra`, `fr_CA`). Les clés ne sont **pas** normalisées : `fr-CA` est donc refusée
  au lieu d'être lue comme `fr_CA` ;
- chaque locale de repli doit elle-même être une clé de `locales` : une chaîne ne se termine jamais sur une locale que
  l'application ne sert pas ;
- une chaîne ne nomme jamais deux fois une locale, y compris la sienne (`fr: [fr]` est refusée).

Rien ne déduit une locale de repli : retirer la région ou se replier sur une langue par défaut n'est jamais fait à
votre place ([dimensions.md](dimensions.md)). Une locale qui n'est pas une clé ici est refusée par chaque méthode
d'écriture de `ContentManager`, répond 404 dans le contrôleur et fait répondre `null` aux fonctions Twig.

### `roots`

| | |
|---|---|
| Type | liste de noms de classes |
| Valeur par défaut | `[]` |
| Branche | `Phil\ContentBundle\Infrastructure\Doctrine\ContentRoots` (avec DoctrineBundle), lu par l'écouteur de mappage, `DoctrineContentRootRepository`, `DoctrinePublishedRowRepository`, `ConstructorContentRootFactory` et `phil:content:list` |

Chaque classe doit être une sous-classe de `Phil\ContentBundle\Domain\Content\AbstractContentRoot`. La liste est
validée par `ContentRoots::fromClassNames()`, que DoctrineBundle soit installé ou non. Une `InvalidContentMapping` est
levée quand :

- une classe n'est pas une sous-classe de `AbstractContentRoot` ;
- deux racines nomment la même classe de ligne (`dimensionContentClass()`) ;
- la clé de ressource d'une racine (`resourceKey()`) n'est pas une lettre minuscule suivie d'au plus 63 lettres
  minuscules, chiffres ou traits de soulignement ;
- deux racines ont la même clé de ressource.

Une classe de ligne qu'aucune racine déclarée ne nomme est refusée quand Doctrine charge ses métadonnées.

### `entity_manager`

| | |
|---|---|
| Type | chaîne (un identifiant de service) |
| Valeur par défaut | `doctrine.orm.entity_manager` |
| Branche | le gestionnaire d'entités passé à `DoctrineDimensionContentRepository`, `DoctrineRouteRepository`, `DoctrineContentRootRepository`, `DoctrinePublishedRowRepository` et `DoctrineContentTransaction` |

Elle ne peut pas être vide. Une valeur qui n'est pas une chaîne est refusée par une `InvalidConfigurationException`
quand le conteneur est construit avec DoctrineBundle. Sans DoctrineBundle, la clé ne sert pas.

Les mappages ORM des classes de base du bundle et de la table de routes sont ajoutés en tête de la configuration
`doctrine` sous sa forme à un seul gestionnaire, que DoctrineBundle applique au gestionnaire d'entités nommé
`default`. Une application qui fait pointer `entity_manager` vers un autre gestionnaire mappe elle-même les deux
dossiers du bundle sur ce gestionnaire (`src/Domain/Content` sous le préfixe `Phil\ContentBundle\Domain\Content`, et
`src/Domain/Routing` sous `Phil\ContentBundle\Domain\Routing`, tous deux de type `attribute`).

### `routes`

| | |
|---|---|
| Type | table de clé de ressource vers un nom de route, ou vers une liste non vide de noms de routes |
| Valeur par défaut | `{}` |
| Branche | `Phil\ContentBundle\Infrastructure\Http\ResourceRoutes`, lu par `ContentController` et `ContentUrlGenerator` |

Les routes sont des routes Symfony que déclare l'application, dont le contrôleur est
`Phil\ContentBundle\Infrastructure\Http\ContentController`. Une chaîne seule est lue comme une liste d'un élément.
**La première route d'un genre est celle sur laquelle ses URL sont générées**, et elle prend un paramètre `{slug}`.
Les routes qui la suivent servent le même genre à leurs propres adresses, d'habitude avec le slug comme valeur par
défaut de la route ([routing.md](routing.md)).

Contraintes, vérifiées à la construction du conteneur :

- les clés ne sont pas normalisées (`static_page` reste `static_page`) ;
- un genre liste au moins une route, et aucun nom de route n'est vide ;
- une route sert un seul genre : une route listée sous deux clés, ou deux fois, est refusée par une
  `InvalidArgumentException`.

Les clés ne sont pas comparées aux racines déclarées : une clé qu'aucune racine ne déclare échoue à la première
requête qui correspond à sa route.

### `templates`

| | |
|---|---|
| Type | table de clé de gabarit vers nom de gabarit Twig |
| Valeur par défaut | `{}` |
| Branche | `Phil\ContentBundle\Application\Rendering\TemplateResolver` |

La clé de gabarit est le `templateKey` de la ligne (`TemplateCapable`). Les clés ne sont pas normalisées ; un nom de
gabarit ne peut pas être vide.

### `default_template`

| | |
|---|---|
| Type | chaîne ou `null` |
| Valeur par défaut | `null` |
| Branche | `Phil\ContentBundle\Application\Rendering\TemplateResolver` |

Le gabarit d'une ligne sans clé de gabarit, d'une ligne dont la clé n'est pas sous `templates`, et d'une classe de
ligne qui n'est pas `TemplateCapable`. Sans lui, une telle ligne fait lever une `LogicException` à
`TemplateResolver::resolve()` : une clé que personne n'a déclarée est une erreur de la configuration, pas de la
requête.

### `preview`

| | |
|---|---|
| Type | table : `secret` (chaîne, non vide), `ttl` (entier, 60 ou plus) |
| Valeur par défaut | `secret: '%kernel.secret%'`, `ttl: 604800` (une semaine) |
| Branche | `Phil\ContentBundle\Infrastructure\Http\PreviewLinks` |

Ce avec quoi les liens d'aperçu sont signés, et combien de secondes ils restent valides
([sitemap-feeds-previews.md](sitemap-feeds-previews.md)).

### `feed_limit`

| | |
|---|---|
| Type | entier, de 1 à 500 |
| Valeur par défaut | `20` |
| Branche | `Phil\ContentBundle\Infrastructure\Http\FeedController` |

Le nombre d'entrées que contient un flux.

### `raw_html_role`

| | |
|---|---|
| Type | chaîne, non vide |
| Valeur par défaut | `null` : personne n'écrit de HTML brut |
| Branche | `Phil\ContentBundle\Infrastructure\Markup\RawHtmlRole`, avec l'alias `Phil\ContentBundle\Application\Contract\RawHtmlPermission` : l'argument `$rawHtml` de `BodyDataMapper` et de `UjoBodyConverter` |
| Exige | SecurityBundle (`symfony/security-core` et son service `security.authorization_checker`) |

Le rôle de confiance dont les titulaires écrivent du HTML brut. Quand l'utilisateur qui publie a ce rôle, le HTML brut
d'un brouillon — écrit en Markdown, BBCode, MediaWiki, DokuWiki, Creole ou un format qu'apporte un module
complémentaire, ou un bloc `raw` d'UJO — est publié comme bloc `raw`, gardé comme le permet le profil `raw` de
`phil/ujo`. Quand l'utilisateur qui enregistre un brouillon a ce rôle, le HTML du brouillon, que montre son aperçu,
produit le rendu de ce HTML brut de la même façon. Tous les autres l'enregistrent et le publient comme du texte
([capabilities.md](capabilities.md#bodycapable)).

### `singles`

| | |
|---|---|
| Type | liste de clés de ressource |
| Valeur par défaut | `[]` |
| Branche | `Phil\ContentBundle\Application\Content\SingleKinds`, lu par `ContentManager` et `ContentRuntime` |

Les genres qui n'ont qu'un seul contenu : la page d'accueil, les coordonnées, la page « à propos ».
`ContentManager::create()` refuse une seconde racine d'un tel genre, `ContentManager::single()` trouve sa racine, et la
fonction Twig `content_single()` la lit ([content-manager.md](content-manager.md),
[rendering.md](rendering.md#les-fonctions-twig)). Une clé qui n'est pas une clé de ressource — une lettre minuscule,
puis jusqu'à 63 lettres minuscules, chiffres ou `_` — est refusée.

### `archives`

| | |
|---|---|
| Type | liste de clés de ressource |
| Valeur par défaut | `[]` |
| Branche | `Phil\ContentBundle\Application\Publication\ContentArchive`, lu par `ArchiveController` et `ContentRuntime` |

Les genres dont chaque publication reste lisible à sa version, avec sa date : une page « maintenant », un journal des
changements, une déclaration qui change avec le temps ([publication.md](publication.md#larchive-datée)). Tout autre
genre garde son historique pour ses rédacteurs.

### `search_index`

| | |
|---|---|
| Type | chaîne, un nom d'index : une lettre minuscule, puis jusqu'à 63 lettres minuscules, chiffres ou `_` |
| Valeur par défaut | `content` |
| Branche | `Phil\ContentBundle\Infrastructure\Search\ContentDocumentProvider` |
| Exige | `phil/search-bundle` dans le noyau |

L'index auquel les contenus publiés sont fournis ([search.md](search.md)).

### `admin`

| Clé | Type | Valeur par défaut | Contient |
|---|---|---|---|
| `enabled` | booléen | `false` | Si l'administration est enregistrée |
| `role` | chaîne | `ROLE_ADMIN` | Le rôle que demande chaque écran de l'administration |
| `layout` | chaîne | `@PhilContent/admin/layout.html.twig` | La mise en page Twig que les écrans étendent, avec un bloc `title` et un bloc `content` |
| `timezone` | chaîne | `UTC` | Le fuseau horaire des moments que l'administration montre et planifie, un identifiant que PHP connaît : `America/Toronto` |

| | |
|---|---|
| Branche | `Phil\ContentBundle\Infrastructure\Http\ContentAdminController` |
| Exige | DoctrineBundle, TwigBundle, SecurityBundle avec la protection CSRF, `symfony/translation` pour ses libellés |

L'administration des contenus ([administration.md](administration.md)).

## Services et conditions

Les services sont enregistrés sous leur nom de classe et sont privés, sauf les contrôleurs — `ContentController`,
`FeedController`, `PreviewController`, `ArchiveController` et `ContentAdminController` —, étiquetés
`controller.service_arguments`, et le workflow `phil_content.publication_workflow`, que `PublicationWorkflowPass` ajoute
à `workflow.registry`. Injectez-les automatiquement par leur classe.

| Service | Enregistré | Configuré par |
|---|---|---|
| `DeclaredLocales` | toujours | `locales` |
| `ContentDataMapper`, `ContentAggregator`, `ContentNormalizer`, les mappeurs de données, fusionneurs et normaliseurs du bundle | toujours | les étiquettes de [pipeline.md](pipeline.md) |
| `DefaultCompiler` (alias `MarkupCompiler`), `SanitizerPurifier` (alias `HtmlPurifier`), de `phil/markup` ; `UjoBodyConverter` (alias `BodyConverter`) | toujours | — |
| `RawHtmlRole` (alias `RawHtmlPermission`) | avec `raw_html_role` | `raw_html_role`, le `security.authorization_checker` du conteneur |
| `ContentWorkflow`, `ContentPublisher`, `ContentVersions`, `DimensionContentCopier`, `LiveContentReader`, `PublishedContents`, `RouteUpdater`, `RouteResolver`, `ContentEditor`, `ContentManager` | toujours, injectés automatiquement sur les ports | le `clock` du conteneur, et son `event_dispatcher` s'il existe |
| `PublicationMarkingStore`, `phil_content.publication_workflow` | toujours ; ajoutés à `workflow.registry` quand le conteneur l'a | l'`event_dispatcher` du conteneur s'il existe |
| `TemplateResolver` | toujours | `templates`, `default_template` |
| `ResourceRoutes` | toujours | `routes` |
| `ContentRoots`, `DimensionContentMappingListener`, les mappages des capacités | avec DoctrineBundle | `roots` |
| `DoctrineDimensionContentRepository`, `DoctrineRouteRepository`, `DoctrineContentRootRepository`, `DoctrinePublishedRowRepository`, `DoctrineContentTransaction`, `ConstructorContentRootFactory`, avec des alias vers leurs ports | avec DoctrineBundle | `entity_manager`, `roots` |
| les cinq commandes `phil:content:*` | avec DoctrineBundle | — |
| `ContentController`, `ContentUrlGenerator`, `ContentExtension`, `ContentRuntime` | avec DoctrineBundle et TwigBundle | `routes`, `templates`, `default_template` |
| `ContentUrlProvider`, `FeedController`, `PreviewController`, `PreviewLinks`, `phil:content:preview-link` | avec DoctrineBundle et TwigBundle | `routes`, `preview`, `feed_limit` |
| `SingleKinds` | toujours | `singles` |
| `ContentArchive` | toujours | `archives` |
| `PageEvents` | toujours ; il construit des événements avec un `event_dispatcher` et `CanonicalAddresses` | — |
| `ContentCanonicalAddresses` (alias `CanonicalAddresses`), `ArchiveController` | avec DoctrineBundle et TwigBundle | `routes`, le mode débogage du noyau |
| `DoctrineWorkingCopyRepository` (alias `WorkingCopyRepository`), `WorkingCopies` | avec DoctrineBundle | `entity_manager`, le `clock` du conteneur |
| `PublicationReferences`, `PublicationSubjects`, `ScheduledTransitionsPublicationSchedule` (alias `PublicationSchedule`) | avec DoctrineBundle et `phil/scheduled-transitions-bundle` | `roots` |
| `ContentDocumentProvider` | avec DoctrineBundle et `phil/search-bundle` | `search_index`, `roots`, `locales` |
| `ContentAdminController` | avec DoctrineBundle, TwigBundle et `admin.enabled` | `admin`, `roots` |

Avec DoctrineBundle, le bundle ajoute aussi en tête de la configuration `doctrine` les types DBAL
`phil_content_dimension_content_id`, `phil_content_locale`, `phil_content_route_id`, `phil_content_stage`,
`phil_content_version` et `phil_content_working_copy_id`, et les mappages `PhilContentBundle`,
`PhilContentBundleRouting` et `PhilContentBundleDraft`.

Chaque classe est listée avec son espace de noms dans [reference.md](reference.md).

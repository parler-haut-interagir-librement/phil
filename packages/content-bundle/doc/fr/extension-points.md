<!-- phil-dev source-sha256=22b9268bb0c6310b261c6d7adae7a31b1bc76d75a88d3b7ff704c884185063d7 -->
# Points d'extension

Ce qu'une application implémente, remplace ou utilise. Chaque port a un adaptateur Doctrine enregistré quand le noyau a
DoctrineBundle. Sans lui, l'application fournit elle-même les six ports de persistance, en faisant de chaque interface
un alias vers son propre service.

## Les ports de persistance

### `DimensionContentRepository`

`Phil\ContentBundle\Domain\Repository\DimensionContentRepository` : charge et enregistre les lignes d'une racine.
Adaptateur Doctrine : `DoctrineDimensionContentRepository`.

| Méthode | Contrat |
|---|---|
| `find(ContentRoot $root, DimensionKey $dimension): ?DimensionContent` | la ligne de `$root` dont la dimension est égale à `$dimension`, ou `null`. Une clé non localisée trouve seulement la ligne non localisée : aucune locale n'en tient lieu |
| `findAll(ContentRoot $root, Stage $stage, Version $version): list<DimensionContent>` | chaque ligne de la racine dans cette étape et cette version : **la ligne non localisée d'abord** quand il y en a une, puis les lignes localisées triées par locale |
| `latestVersion(ContentRoot $root, Stage $stage): ?Version` | la plus haute version qui a au moins une ligne de la racine dans l'étape, localisée ou non ; `null` s'il n'y en a aucune |
| `latestLocalizedVersion(ContentRoot $root, Stage $stage, Locale $locale): ?Version` | la plus haute version qui a une ligne de la locale dans l'étape ; `null` s'il n'y en a aucune |
| `findVersions(ContentRoot $root, Stage $stage, Locale $locale): list<DimensionContent>` | chaque ligne de la locale dans l'étape, une par version, la plus récente d'abord : l'historique d'une locale en une seule recherche |
| `add(DimensionContent $row): void` | enregistre une nouvelle ligne, écrite quand la transaction vide vers la base |
| `remove(DimensionContent $row): void` | supprime une ligne enregistrée quand la transaction vide vers la base |

Les invariants que respecte un adaptateur :

- **une seule ligne par `(root, stage, version, locale or none)`** ; une seconde ligne de la même dimension est refusée
  par le stockage (une contrainte d'unicité), jamais gardée sans bruit ;
- une ligne retirée et une ligne de la même dimension ajoutée sont écrites dans cet ordre quand
  `ContentTransaction::flush()` est appelée entre les deux ;
- `find()` et `findAll()` voient les lignes ajoutées et retirées dans la transaction en cours une fois qu'elle a vidé
  vers la base.

### `RouteRepository`

`Phil\ContentBundle\Domain\Repository\RouteRepository` : adaptateur Doctrine `DoctrineRouteRepository`.

| Méthode | Contrat |
|---|---|
| `findBySlug(string $resourceKey, Locale $locale, Slug $slug): ?Route` | la route, actuelle ou d'historique, qui détient le slug pour le genre dans la locale |
| `findCurrent(ContentReference $content, Locale $locale): ?Route` | la route actuelle de la racine dans la locale ; jamais une route d'historique |
| `findCurrentAmong(string $resourceKey, Locale $locale, list<string> $resourceIds): list<Route>` | les routes actuelles dans la locale des contenus d'un genre nommés par leurs identifiants, en une seule recherche ; un contenu qui n'en a pas est absent, sans ordre |
| `findAllOf(ContentReference $content): list<Route>` | chaque route de la racine, actuelle et d'historique, dans chaque locale |
| `add(Route $route): void`, `remove(Route $route): void` | enregistrer et supprimer, écrit quand la transaction vide vers la base |

Invariant : **au plus une route par `(resource key, locale, slug)`**, et au plus une route actuelle par racine et par
locale (la seconde est respectée par `RouteUpdater`, qui retire la route actuelle précédente).

### `ContentRootRepository`

`Phil\ContentBundle\Domain\Repository\ContentRootRepository` : adaptateur Doctrine `DoctrineContentRootRepository`.

| Méthode | Contrat |
|---|---|
| `find(ContentReference $reference): ?ContentRoot` | la racine que nomme la référence, ou `null` quand aucune n'existe ou que l'identifiant ne peut pas être un identifiant de son genre |
| `findAll(string $resourceKey): list<ContentRoot>` | chaque racine du genre |
| `referenceOf(ContentRoot $root): ContentReference` | le nom de la racine hors du stockage ; lève `InvalidContent` quand son identifiant n'a pas de forme en chaîne (ou n'est pas encore défini) |
| `add(ContentRoot $root): void` | enregistre une nouvelle racine |
| `remove(ContentRoot $root): void` | la supprime quand la transaction vide vers la base ; ses lignes et ses routes sont d'abord retirées par l'appelant (`ContentManager::delete()`) |

Invariant : `find($this->referenceOf($root))` renvoie `$root`.

### `PublishedRowRepository`

`Phil\ContentBundle\Domain\Repository\PublishedRowRepository` : lit les lignes publiées d'un genre en quelques
requêtes, quel que soit le nombre de racines. C'est ce que lisent le plan du site, les flux et les listes d'une
application. Adaptateur Doctrine : `DoctrinePublishedRowRepository`.

| Méthode | Contrat |
|---|---|
| `findPublished(PublishedRowQuery $query): list<PublishedRow>` | pour chaque racine du genre qui a une ligne en ligne de la locale de la requête, cette ligne dans la plus haute version à partir de laquelle la locale a été publiée, associée à la ligne partagée en ligne de la même version (ou `null`) ; filtrée, triée et coupée comme le dit la requête |

Une `PublishedRowQuery` nomme la clé de ressource et la locale, puis :

| Argument | Valeur par défaut | Sens |
|---|---|---|
| `order` | `PublishedOrder::Root` | `Root`, par l'identifiant de la racine ; `LatestPublished`, les plus récemment publiés d'abord et ceux qui n'ont jamais été publiés à la fin ; `Slug`, par le slug de la ligne localisée. Chaque ordre se termine sur l'identifiant de la racine, pour que les pages se suivent sans chevauchement ; une classe de ligne sans la capacité que lit un ordre est triée par racine |
| `rootCriteria` | `[]` | des champs de la racine et les valeurs auxquelles ils sont égaux, `['genre' => 'podcast']` ; un champ que la racine ne mappe pas donne une `InvalidContentMapping` |
| `resourceIds` | `null` | les racines gardées, par identifiant ; `null` les garde toutes, `[]` aucune ; un identifiant que son type refuse ne correspond à rien |
| `withSlug` | `false` | garde les lignes qui ont un slug, les seules qu'une route sert ; aucune quand la classe de ligne n'est pas `RoutableCapable` |
| `offset`, `limit` | `0`, `null` | la page découpée dans l'ordre ; un décalage négatif ou une limite inférieure à un donne une `InvalidContent` |

L'adaptateur Doctrine lit une page en deux requêtes : les lignes localisées avec leurs racines (la plus haute version
par un `MAX` corrélé sur le préfixe `(root_id, stage)` de l'index unique), puis les lignes partagées de ces racines dans
une seule liste `IN`, les identifiants des racines liés comme leur propre type DBAL les enregistre.

### `ContentTransaction`

`Phil\ContentBundle\Application\Persistence\ContentTransaction` : adaptateur Doctrine `DoctrineContentTransaction`.

| Méthode | Contrat |
|---|---|
| `run(callable $operation): mixed` | exécute l'opération dans une transaction, vide vers la base et valide, puis renvoie son résultat ; annule et relance l'exception en cas d'échec. Dans une transaction déjà ouverte, elle s'imbrique (un point de sauvegarde) |
| `flush(): void` | écrit ce qui est en attente maintenant, dans la transaction en cours |

Invariant : **une opération est gardée en entier, ou pas du tout**.

### `ContentRootFactory`

`Phil\ContentBundle\Application\Content\ContentRootFactory` : adaptateur Doctrine `ConstructorContentRootFactory`, qui
appelle le constructeur de la racine sans arguments.

| Méthode | Contrat |
|---|---|
| `create(string $resourceKey): ContentRoot` | une nouvelle racine du genre, non enregistrée ; `ContentManager::create()` l'ajoute au dépôt |

Une application dont les racines prennent des arguments de constructeur fait de cette interface un alias vers sa
propre fabrique :

```php
namespace App\Content;

use Phil\ContentBundle\Application\Content\ContentRootFactory;
use Phil\ContentBundle\Domain\Content\ContentRoot;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(ContentRootFactory::class)]
final readonly class SiteContentRootFactory implements ContentRootFactory
{
    public function __construct(private CurrentSite $site)
    {
    }

    public function create(string $resourceKey): ContentRoot
    {
        return match ($resourceKey) {
            'news' => new News($this->site->id()),
            'static_page' => new StaticPage($this->site->id()),
        };
    }
}
```

### Un adaptateur sans Doctrine

Sans DoctrineBundle, faites des six ports des alias dans `config/services.yaml` :

```yaml
services:
    Phil\ContentBundle\Domain\Repository\DimensionContentRepository: '@App\Content\Storage\Rows'
    Phil\ContentBundle\Domain\Repository\RouteRepository: '@App\Content\Storage\Routes'
    Phil\ContentBundle\Domain\Repository\ContentRootRepository: '@App\Content\Storage\Roots'
    Phil\ContentBundle\Domain\Repository\PublishedRowRepository: '@App\Content\Storage\PublishedRows'
    Phil\ContentBundle\Application\Persistence\ContentTransaction: '@App\Content\Storage\Transaction'
    Phil\ContentBundle\Application\Content\ContentRootFactory: '@App\Content\Storage\Factory'
```

Le pipeline, les services de publication et `ContentManager` fonctionnent alors sur eux. Le contrôleur, les fonctions
Twig et les commandes de console ne sont enregistrés qu'avec DoctrineBundle.

## Les classes de contenu

### `ContentRoot` et `DimensionContent`

`Domain\Content\ContentRoot` est tout ce dont le pipeline a besoin d'une racine :
`createDimensionContent(DimensionKey): DimensionContent`, une ligne neuve et vide de la classe de ligne propre à
l'application, rattachée à la racine. Elle sert de cible aux fusions et aux copies, pour que le résultat porte les
mêmes capacités. `Domain\Content\DimensionContent` est une ligne : `root(): ContentRoot` et
`dimension(): DimensionKey`.

Avec les adaptateurs Doctrine, les racines étendent `AbstractContentRoot` et les lignes étendent
`AbstractDimensionContent` ([storage.md](storage.md)) ; avec des adaptateurs de l'application, toute classe qui
implémente les deux interfaces fonctionne.

## Les étapes du pipeline

`DataMapper`, `Merger` et `Normalizer` (`Phil\ContentBundle\Application\Pipeline`) sont étiquetés par configuration
automatique ; leurs contrats sont dans [pipeline.md](pipeline.md). Les règles que respecte une étape de l'application :

- un **mappeur de données** traite une capacité, ignore les lignes qui ne l'ont pas, respecte « une clé absente ne
  change rien, `null` efface », et écrit des clés qu'aucun autre mappeur n'écrit ;
- un **fusionneur** copie seulement ce que sa source définit, et ne lit jamais rien d'autre que `$source` et
  `$target` ; il s'exécute pour l'agrégation **et** pour les copies (publication, nouvelles versions) : une capacité
  sans fusionneur n'est donc ni lue ni publiée ;
- un **normaliseur** renvoie un tableau vide pour une ligne sans sa capacité, et des clés qu'aucun autre normaliseur
  ne renvoie (`ContentNormalizer` refuse une clé répétée par une `LogicException`).

### Une capacité propre à l'application

```php
namespace App\Content\Capability;

interface EventDatesCapable
{
    public function getStartsAt(): ?\DateTimeImmutable;
    public function setStartsAt(?\DateTimeImmutable $startsAt): void;
}

trait EventDatesTrait
{
    private ?\DateTimeImmutable $startsAt = null;

    public function getStartsAt(): ?\DateTimeImmutable { return $this->startsAt; }
    public function setStartsAt(?\DateTimeImmutable $startsAt): void { $this->startsAt = $startsAt; }
}
```

```php
use Phil\ContentBundle\Application\DataMapper\SubmittedData;
use Phil\ContentBundle\Application\Pipeline\{DataMapper, Merger, Normalizer};
use Phil\ContentBundle\Domain\Content\DimensionContent;
use Phil\ContentBundle\Infrastructure\Doctrine\Mapping\CapabilityMapping;

final class EventDatesCapabilityMapping extends CapabilityMapping
{
    public function capability(): string { return EventDatesCapable::class; }

    protected function fields(): array
    {
        return [['fieldName' => 'startsAt', 'type' => 'datetime_immutable', 'columnName' => 'starts_at', 'nullable' => true]];
    }
}

final readonly class EventDatesDataMapper implements DataMapper
{
    public function map(DimensionContent $unlocalized, DimensionContent $localized, array $data): void
    {
        if ($unlocalized instanceof EventDatesCapable && SubmittedData::has($data, 'startsAt')) {
            $value = SubmittedData::string($data, 'startsAt');
            $unlocalized->setStartsAt(null === $value ? null : new \DateTimeImmutable($value));   // shared by every locale
        }
    }
}

final readonly class EventDatesMerger implements Merger
{
    public function merge(DimensionContent $target, DimensionContent $source): void
    {
        if ($target instanceof EventDatesCapable && $source instanceof EventDatesCapable && null !== $source->getStartsAt()) {
            $target->setStartsAt($source->getStartsAt());
        }
    }
}

final readonly class EventDatesNormalizer implements Normalizer
{
    public function normalize(DimensionContent $content): array
    {
        return $content instanceof EventDatesCapable
            ? ['startsAt' => $content->getStartsAt()?->format(\DATE_RFC3339)]
            : [];
    }
}
```

Avec la configuration automatique activée, les quatre services rejoignent ceux du bundle. Un champ listé par un
`CapabilityMapping` porte le nom de la propriété du trait ; une propriété d'énumération typée nomme sa classe sous
`enumType`. Un champ que la classe de ligne mappe elle-même est laissé tel quel.

## Les ports de balisage

### `MarkupCompiler` et `HtmlPurifier`

| Port | Méthode | Contrat | Adaptateur par défaut |
|---|---|---|---|
| `Phil\Markup\MarkupCompiler` | `compile(string $source, MarkupFormat $format): string` | la source compilée en HTML. **Le résultat n'est pas sûr à afficher** : un compilateur ne fait que traduire | `DefaultCompiler` |
| `Phil\Markup\HtmlPurifier` | `purify(string $html, Level $level): string` | retire tout ce qui pourrait s'exécuter dans le navigateur d'un lecteur ou atteindre l'extérieur de la page : scripts, gestionnaires d'événements, URL `javascript:`, cadres, formulaires, styles. Appliqué au niveau `Level::All` avant l'enregistrement, et de nouveau à chaque lecture | `SanitizerPurifier` (`defaultPolicies()`) |

Remplacez l'un ou l'autre en faisant de l'interface un alias vers votre propre service :

```php
namespace App\Content;

use Phil\Markup\HtmlPurifier;
use Phil\Markup\Level;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;

#[AsAlias(HtmlPurifier::class)]
final readonly class StrictPurifier implements HtmlPurifier
{
    public function __construct(private HtmlSanitizerInterface $contentSanitizer)   // a sanitizer of framework.html_sanitizer
    {
    }

    public function purify(string $html, Level $level): string
    {
        return $this->contentSanitizer->sanitize($html);
    }
}
```

Gardez un purificateur idempotent — du HTML qu'il a déjà purifié revient inchangé —, puisque chaque corps est purifié à
l'écriture et de nouveau à la lecture.

### `BodyConverter`

| Port | Méthode | Contrat | Adaptateur par défaut |
|---|---|---|---|
| `Phil\ContentBundle\Application\Publication\BodyConverter` | `toUjo(Body $body, DateTimeImmutable $publishedAt): Body` | le corps qu'enregistre une ligne publiée : la source convertie en UJO, son `time` étant le moment `$publishedAt` en millisecondes, le format `ujo`, le HTML compilé à partir de cet UJO et purifié ; `InvalidContent` quand la source ne peut pas être lue | `UjoBodyConverter` |

Une application qui lit plus de formats, ou à un autre niveau, fait du port un alias vers son propre service avec
`#[AsAlias(BodyConverter::class)]`. Le corps qu'il renvoie est enregistré tel quel : purifiez son HTML.

### `ConversionLosses`

| Port | Méthode | Contrat | Adaptateur par défaut |
|---|---|---|---|
| `Phil\ContentBundle\Application\Contract\ConversionLosses` | `of(string $source, MarkupFormat\|string $format): list<ConversionLoss>` | ce que la conversion en UJO ne garde pas, dans l'ordre de la source — chaque `ConversionLoss` étant un élément, son chemin et la raison que nomme `phil/markup` —, lu comme `toUjo()` le lit ; `InvalidContent` quand la source ne peut pas être lue | `UjoBodyConverter` |

`ContentPublisher` refuse une publication dont le corps perd du contenu, et `phil:content:publish` et l'administration
montrent les pertes avant de publier
([publication.md](publication.md#une-publication-qui-perd-du-contenu-est-refusée)). Sans implémentation, rien n'est
refusé pour ses pertes.

### `CanonicalAddresses`

| Port | Méthode | Contrat | Adaptateur par défaut |
|---|---|---|---|
| `Phil\ContentBundle\Application\Contract\CanonicalAddresses` | `of(ContentReference $content, Locale $locale): ?Url` | l'adresse absolue du contenu dans la locale, un `Phil\Url\Url`, ou `null` quand il n'en a pas | `ContentCanonicalAddresses`, avec TwigBundle : l'URL absolue de la route actuelle sur le routeur, `localhost` accepté seulement en mode débogage |

Les événements de page la portent ([publication.md](publication.md#les-événements-de-page)). Une application dont les
pages vivent sur plusieurs hôtes — un site par locataire — fait de ce port un alias de son propre service avec
`#[AsAlias(CanonicalAddresses::class)]`.

### `PublicationSchedule`

| Port | Méthodes | Adaptateur par défaut |
|---|---|---|
| `Phil\ContentBundle\Application\Contract\PublicationSchedule` | `schedule(Publication, string $transition, DateTimeImmutable $at)`, `cancel(Publication, ?string $transition = null): int`, `pending(Publication): list<ScheduledPublication>` | `ScheduledTransitionsPublicationSchedule`, avec `phil/scheduled-transitions-bundle` |

Une application dotée de son propre planificateur l'implémente, en appliquant `publish` ou `unpublish` par le workflow
`phil_content_publication` à l'échéance ([publication.md](publication.md#publication-planifiée)).

## Copies de travail

### `WorkingCopyRepository`

| Port | Méthodes | Adaptateur par défaut |
|---|---|---|
| `Phil\ContentBundle\Domain\Contract\WorkingCopyRepository` | `findAutosave(ContentReference, Locale, string $author)`, `findNamed(ContentReference, Locale, string $name)`, `findAllNamed(ContentReference, Locale)` par nom, `findAllOf(ContentReference)`, `add()`, `remove()` | `DoctrineWorkingCopyRepository`, avec DoctrineBundle |

Il n'écrit rien lui-même : `WorkingCopies` et `ContentManager::delete()` l'exécutent dans une `ContentTransaction`.

## Recherche

`ContentDocumentProvider` est un `DocumentProvider` de `phil/search-bundle`, enregistré avec ce bundle. Une application
qui veut d'autres champs le décore, ou écrit son propre fournisseur pour les mêmes lignes en ligne et laisse
`search_index` à un index à elle ([search.md](search.md)).

## Événements de page

`ContentManager` répartit `Phil\Publishing\PagePublished` et `Phil\Publishing\PageRetired` de `phil/publishing` sur
l'`event_dispatcher` du conteneur ([publication.md](publication.md#les-événements-de-page)). Un écouteur de l'un ou de
l'autre suit ce que les lecteurs peuvent voir.

### `RawHtmlPermission`

| Port | Méthode | Contrat | Adaptateur par défaut |
|---|---|---|---|
| `Phil\ContentBundle\Application\Contract\RawHtmlPermission` | `isGranted(): bool` | si l'utilisateur au travail écrit du HTML brut : `BodyDataMapper` compile alors son brouillon pour `Level::All` et `UjoBodyConverter` publie des blocs `raw` | `RawHtmlRole`, avec `raw_html_role` |

Une application qui décide selon autre chose qu'un rôle — sa propre permission, un *voter* sur le contenu — fait du
port un alias vers son propre service avec `#[AsAlias(RawHtmlPermission::class)]`. Il répond pour la requête en cours,
et ne garde aucun état entre deux requêtes.

## Les événements du workflow

Quand le conteneur a un `event_dispatcher`, le workflow nommé `phil_content` répartit les événements de
`symfony/workflow` (`workflow.phil_content.guard.<transition>`, `workflow.phil_content.completed.<transition>`, …) avec
la ligne de brouillon localisée comme sujet. Une protection qui bloque fait lever `TransitionRefused` à l'opération
([publication.md](publication.md#protéger-une-transition)).

## Identifiants de service, étiquettes et constantes

| Quoi | Valeur |
|---|---|
| Alias de configuration | `phil_content` |
| Étiquette des mappeurs de données | `phil_content.data_mapper` (`PhilContentBundle::DATA_MAPPER_TAG`) |
| Étiquette des fusionneurs | `phil_content.merger` (`PhilContentBundle::MERGER_TAG`) |
| Étiquette des normaliseurs | `phil_content.normalizer` (`PhilContentBundle::NORMALIZER_TAG`) |
| Étiquette des mappages de capacités | `phil_content.capability_mapping` (`PhilContentBundle::CAPABILITY_MAPPING_TAG`), avec DoctrineBundle |
| Noms des workflows | `phil_content` (`ContentWorkflow::NAME`), celui de la ligne de brouillon ; `phil_content_publication` (`PublicationWorkflow::NAME`), celui d'un contenu dans une locale, service `phil_content.publication_workflow`, ajouté à `workflow.registry` quand il existe |
| Services | chaque service sous son nom de classe ([configuration.md](configuration.md#services-et-conditions)) ; les ports `MarkupCompiler`, `HtmlPurifier`, `BodyConverter`, `ConversionLosses`, `CanonicalAddresses`, `PublicationSchedule`, `DimensionContentRepository`, `RouteRepository`, `ContentRootRepository`, `PublishedRowRepository`, `WorkingCopyRepository`, `ContentTransaction` et `ContentRootFactory` sont des alias |
| Étiquettes d'autres bundles | `phil_sitemap.url_provider` sur `ContentUrlProvider` ; `phil_scheduled_transitions.subject_resolver` sur `PublicationSubjects` ; `phil_search.provider` sur `ContentDocumentProvider` — nommées par leurs chaînes, sans dépendre de ces bundles |
| Services du conteneur utilisés | `clock` ; s'il existe, `event_dispatcher` ; avec TwigBundle, `twig`, `router`, `request_stack` ; avec l'administration, `security.token_storage`, `security.authorization_checker`, `security.csrf.token_manager` |
| Types DBAL | `phil_content_locale`, `phil_content_stage`, `phil_content_version`, `phil_content_dimension_content_id`, `phil_content_route_id`, `phil_content_working_copy_id` |
| Tables | `phil_content_route`, `phil_content_working_copy` |
| Écouteur Doctrine | `DimensionContentMappingListener` sur `loadClassMetadata` |
| Commandes de console | `phil:content:create`, `phil:content:edit`, `phil:content:publish`, `phil:content:unpublish`, `phil:content:list`, `phil:content:preview-link` |
| Fonctions Twig | `content`, `content_single`, `content_archive`, `content_url`, `content_link_url`, `content_seo`, `content_schema` |
| Variables des gabarits | `content`, `root`, `reference`, `locale`, `resolved_locale`, `left_language` ; sur une page d'archive, `archived` et `archive` |
| Routes de l'administration | `phil_content_admin_list`, `phil_content_admin_create`, `phil_content_admin_edit`, `phil_content_admin_autosave` (`config/routes_admin.php`) |
| Domaine de traduction | `phil_content`, en anglais et en français |
| Contrôleur Stimulus | `phil--content-bundle--autosave` |
| Identifiant du jeton CSRF | `phil_content_admin` |

Le bundle ne déclare aucun attribut PHP ; les événements qu'il répartit sont ceux de `phil/publishing`.

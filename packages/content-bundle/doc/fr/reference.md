<!-- phil-dev source-sha256=1e953d2662f00d5d2833337646aa36175e8428de80a1b20409442757cbbf41f9 -->
# Référence de l'API

Chaque classe et interface du bundle, par couche. Tous les espaces de noms sont sous `Phil\ContentBundle`. « Port »
désigne une interface que l'application peut implémenter ou remplacer ; « implémenté », une classe que fournit le
bundle. Chaque entrée ici est implémentée et testée ; ce qui n'est pas commencé est listé à la fin.

## La classe du bundle

| Classe | Rôle |
|---|---|
| `PhilContentBundle` | L'`AbstractBundle` : arbre de configuration sous `phil_content`, constantes d'étiquettes (`DATA_MAPPER_TAG`, `MERGER_TAG`, `NORMALIZER_TAG`, `CAPABILITY_MAPPING_TAG`), configuration Doctrine ajoutée en tête avec DoctrineBundle, et services ([configuration.md](configuration.md)) |

## Domaine

### `Domain\Content`

| Classe | Genre | Rôle |
|---|---|---|
| `ContentRoot` | port | Une racine : `createDimensionContent(DimensionKey)` |
| `DimensionContent` | port | Une ligne : `root()`, `dimension()` |
| `AbstractContentRoot` | implémenté, superclasse mappée | Base des entités racines de l'application : `dimensionContentClass()`, `resourceKey()` |
| `AbstractDimensionContent` | implémenté, superclasse mappée | Base des entités de ligne de l'application : colonnes d'identifiant et de dimension, constructeur final |
| `DimensionContentId` | implémenté | Identifiant de substitution UUIDv7 d'une ligne (`Identifier` de `phil/ddd-bundle`) |

### `Domain\Dimension`

| Classe | Genre | Rôle |
|---|---|---|
| `DimensionKey` | implémenté | Locale (ou aucune), étape et version d'une ligne |
| `Stage` | implémenté, énumération | `Draft`, `Live` |
| `DimensionLocales` | implémenté | Une locale lue et sa chaîne trouvée, refusées par `InvalidDimension` |

`Locale`, `Version`, `FallbackChain` et `DeclaredLocales` sont les objets valeur de `phil/locale-dimensions`.

### `Domain\Capability`

| Classe | Genre | Rôle |
|---|---|---|
| `TemplateCapable`, `TemplateTrait` | port et trait | Clé de gabarit et valeurs des champs |
| `WorkflowCapable`, `WorkflowTrait` | port et trait | Place dans le workflow et date de la dernière publication |
| `SeoCapable`, `SeoTrait` | port et trait | Titre, description, mots-clés et URL canonique de référencement, trois directives |
| `ExcerptCapable`, `ExcerptTrait` | port et trait | Titre, description et référence d'image d'accroche |
| `LinkCapable`, `LinkTrait` | port et trait | Un `Link` |
| `RoutableCapable`, `RoutableTrait` | port et trait | Un `Slug` |
| `BodyCapable`, `BodyTrait` | port et trait | Un `Body` |
| `CapabilityText` | implémenté | Les vérifications des valeurs de texte libre (`text()`, `line()`, `reference()`) |

### `Domain\ValueObject`

| Classe | Rôle |
|---|---|
| `Body` | Source, `MarkupFormat` et HTML compilé d'un texte principal |
| `ContentReference` | `resource_key:identifier`, une racine nommée depuis l'extérieur du stockage |
| `Link` | Une racine de contenu (clé, identifiant, locale facultative) ou une `Url` de `phil/url` |
| `Slug` | Des mots en minuscules reliés par des traits d'union, d'au plus 255 caractères |

### `Domain\Routing`

| Classe | Rôle |
|---|---|
| `Route` | Entité de `phil_content_route` : une adresse, actuelle ou d'historique |
| `RouteId` | Identifiant de substitution UUIDv7 d'une route |

### `Domain\Draft`

| Classe | Rôle |
|---|---|
| `WorkingCopy` | Entité de `phil_content_working_copy` : l'enregistrement automatique d'un auteur ou un brouillon nommé d'un contenu dans une locale |
| `WorkingCopyId` | Identifiant UUIDv7 d'une copie de travail |

### `Domain\Contract`

| Interface | Genre | Rôle |
|---|---|---|
| `WorkingCopyRepository` | port | Les enregistrements automatiques par auteur, les brouillons nommés par nom, toutes les copies de travail d'un contenu |

### `Domain\Repository`

| Interface | Genre | Rôle |
|---|---|---|
| `DimensionContentRepository` | port | Les lignes par dimension, par étape et version, les versions d'une locale, les dernières versions |
| `RouteRepository` | port | Les routes par slug et par contenu, les routes actuelles de plusieurs contenus |
| `ContentRootRepository` | port | Les racines par référence, les références des racines |
| `PublishedRowRepository` | port | Une page des lignes publiées d'un genre dans une locale |
| `PublishedRowQuery`, `PublishedOrder`, `PublishedRow` | valeur | Ce que lit `PublishedRowRepository`, dans quel ordre, et chaque ligne trouvée avec sa ligne partagée |

### `Domain\Exception`

Chaque classe implémente `Phil\DddBundle\Domain\Exception\DomainError`.

| Classe | Étend | Quand |
|---|---|---|
| `InvalidDimension` | `InvalidArgumentException` | Une locale mal formée ou non servie, l'`InvalidLocaleDimension` de `phil/locale-dimensions` gardée comme précédente ; une ligne fusionnée sur une autre qui n'est pas sa contrepartie |
| `InvalidContent` | `InvalidArgumentException` | Une valeur de capacité, une référence, un slug, une URL ou un lien refusés |
| `ContentNotFound` | `RuntimeException` | Aucune ligne, racine, contenu en ligne, enregistrement automatique ou brouillon nommé là où il en faut un |
| `SlugTaken` | `RuntimeException` | Le slug est l'adresse actuelle d'une autre racine |
| `TransitionRefused` | `RuntimeException` | Le workflow ne permet pas l'opération |

## Application

| Classe | Genre | Rôle |
|---|---|---|
| `ContentManager` | implémenté | L'interface PHP : créer, modifier, publier, dépublier, retirer un brouillon, supprimer, lire, lister, état |
| `ContentStatus` | implémenté | L'endroit où se trouve une locale d'une racine |
| `Content\SingleKinds` | implémenté | Les genres qui n'ont qu'un seul contenu |
| `Contract\CanonicalAddresses` | port | L'adresse absolue d'un contenu dans une locale |
| `Contract\PublicationSchedule` | port | Publier ou mettre hors ligne à un moment fixé |
| `Exception\PublicationLosesContent` | exception | Une publication refusée pour ce que son corps perdrait en UJO, avec les pertes |
| `Editing\WorkingCopies` | implémenté | L'enregistrement automatique privé de chaque auteur et les brouillons nommés d'un contenu |
| `Editing\AuthorRawHtml` | implémenté | La permission d'écrire du HTML brut de l'auteur de la révision en cours : l'utilisateur au travail, ou l'auteur de la copie de travail appliquée |
| `Publication\PageEvents` | implémenté | Les événements `PagePublished` et `PageRetired` de `phil/publishing`, lus sur la ligne en ligne |
| `Publication\ContentArchive`, `Publication\ArchivedVersion` | implémenté, valeur | L'archive datée d'un genre, et une publication qu'elle contient |
| `Publication\ScheduledPublication` | valeur | Une transition prévue pour un moment |
| `Content\ContentRootFactory` | port | Construit une nouvelle racine d'un genre |
| `Persistence\ContentTransaction` | port | L'unité de travail d'une opération |
| `Pipeline\DataMapper`, `Pipeline\Merger`, `Pipeline\Normalizer` | ports | Les trois étapes du pipeline |
| `Pipeline\ContentDataMapper` | implémenté | Exécute chaque mappeur de données |
| `Pipeline\ContentAggregator` | implémenté | Fusionne une ligne localisée par-dessus sa ligne partagée, le long d'une chaîne |
| `Pipeline\ContentNormalizer` | implémenté | Exécute chaque normaliseur, refuse une clé répétée |
| `DataMapper\TemplateDataMapper`, `SeoDataMapper`, `ExcerptDataMapper`, `LinkDataMapper`, `RoutableDataMapper`, `BodyDataMapper` | implémenté | Un mappeur de données par capacité (aucun pour le workflow) |
| `DataMapper\SubmittedData` | implémenté | Lecteurs typés des données soumises |
| `Contract\RawHtmlPermission` | port | Si l'utilisateur au travail a le rôle de confiance : les corps qu'il écrit le retiennent, montrent son HTML brut et le gardent une fois publiés, par n'importe qui |
| `Merger\TemplateMerger`, `WorkflowMerger`, `SeoMerger`, `ExcerptMerger`, `LinkMerger`, `RoutableMerger`, `BodyMerger` | implémenté | Un fusionneur par capacité |
| `Normalizer\TemplateNormalizer`, `WorkflowNormalizer`, `SeoNormalizer`, `ExcerptNormalizer`, `LinkNormalizer`, `RoutableNormalizer`, `BodyNormalizer` | implémenté | Un normaliseur par capacité |
| `Editing\ContentEditor` | implémenté | Écrit des données soumises sur le brouillon d'une locale, en créant versions et lignes au besoin |
| `Publication\ContentWorkflow` | implémenté | La machine à états de `symfony/workflow` |
| `Publication\ContentPublisher` | implémenté | Publier, dépublier, retirer un brouillon ; un corps publié en UJO |
| `Publication\BodyConverter` | port | Le corps d'un brouillon converti en l'UJO qu'enregistre une ligne publiée |
| `Contract\ConversionLosses`, `Publication\ConversionLoss` | port, valeur | Ce que la conversion d'un corps en UJO ne garde pas |
| `Publication\ContentVersions` | implémenté | Dernière version de brouillon, versions figées, nouvelles versions |
| `Publication\DimensionContentCopier` | implémenté | Copie une ligne vers une autre dimension par les fusionneurs |
| `Publication\LiveContentReader` | implémenté | Ce qui est servi à un lecteur le long d'une chaîne |
| `Routing\RouteUpdater` | implémenté | Disponibilité des slugs et routes actuelles |
| `Routing\RouteResolver` | implémenté | D'un slug à une route à servir ou vers laquelle rediriger |
| `Routing\RouteMatch` | implémenté | Le résultat d'une résolution |
| `Rendering\TemplateResolver` | implémenté | De la clé de gabarit au gabarit Twig |

## Infrastructure

### `Infrastructure\Doctrine`

| Classe | Rôle |
|---|---|
| `DoctrineDimensionContentRepository` | Adaptateur de `DimensionContentRepository` |
| `DoctrineRouteRepository` | Adaptateur de `RouteRepository` |
| `DoctrineContentRootRepository` | Adaptateur de `ContentRootRepository` |
| `DoctrinePublishedRowRepository` | Adaptateur de `PublishedRowRepository` |
| `RootIdentifiers` | L'identifiant d'une racine tiré de sa chaîne, et lié dans une liste `IN` comme son type DBAL l'enregistre |
| `DoctrineContentTransaction` | Adaptateur de `ContentTransaction` |
| `ConstructorContentRootFactory` | Adaptateur de `ContentRootFactory`, constructeur sans arguments |
| `ContentRoots` | Les classes racines déclarées, leurs classes de ligne et leurs clés de ressource |
| `DimensionContentMappingListener` | `loadClassMetadata` : association à la racine, dimension unique, colonnes des capacités |
| `InvalidContentMapping` | `LogicException` pour des classes de contenu qui ne s'accordent pas |
| `Mapping\CapabilityMapping` | Base abstraite des colonnes d'une capacité (point d'extension) |
| `Mapping\TemplateCapabilityMapping`, `WorkflowCapabilityMapping`, `SeoCapabilityMapping`, `ExcerptCapabilityMapping`, `LinkCapabilityMapping`, `RoutableCapabilityMapping`, `BodyCapabilityMapping` | Les colonnes de chaque capacité |
| `DoctrineWorkingCopyRepository` | Adaptateur de `WorkingCopyRepository` |
| `Type\LocaleType`, `StageType`, `VersionType`, `DimensionContentIdType`, `RouteIdType`, `WorkingCopyIdType` | Les types DBAL |

### `Infrastructure\Http`

| Classe | Rôle |
|---|---|
| `ContentController` | Sert le contenu en ligne par slug : 200, 301, 404 |
| `ContentUrlGenerator` | L'URL de la route actuelle d'une racine |
| `ResourceRoutes` | Les routes Symfony qui servent chaque genre |
| `ContentCanonicalAddresses` | Adaptateur de `CanonicalAddresses` sur le routeur |
| `ArchiveController` | Sert une version datée d'un contenu d'un genre archivé |
| `ContentAdminController` | L'administration : liste, création, écran de modification, enregistrement automatique |
| `FeedController`, `PreviewController`, `PreviewLinks` | Les flux Atom, les aperçus de brouillons et leurs liens signés |

### `Infrastructure\EasyAdmin`

| Classe | Rôle |
|---|---|
| `ContentAdmin` | L'entrée de menu d'un tableau de bord EasyAdmin qui mène à l'administration |

### `Infrastructure\Scheduling`

| Classe | Rôle |
|---|---|
| `ScheduledTransitionsPublicationSchedule` | Adaptateur de `PublicationSchedule` sur `phil/scheduled-transitions-bundle` |
| `PublicationReferences` | Une publication nommée `news:42@fr` pour ce bundle, et relue |
| `PublicationSubjects` | Le résolveur par lequel le gestionnaire de workflow de ce bundle trouve les publications |

### `Infrastructure\Search`

| Classe | Rôle |
|---|---|
| `ContentDocumentProvider` | Le `DocumentProvider` de `phil/search-bundle` qui lui fournit les lignes en ligne |

### `Infrastructure\Markup`

| Classe | Rôle |
|---|---|
| `Markup\MarkupServices` | Enregistre les adaptateurs par défaut de `phil/markup` et le convertisseur du bundle, et fait des trois ports des alias vers eux |
| `Markup\UjoBodyConverter` | Adaptateur de `BodyConverter` et de `ConversionLosses` : les lecteurs de `phil/markup`, l'encodeur de `phil/ujo`, compilé et purifié |
| `Markup\RawHtmlRole` | Adaptateur de `RawHtmlPermission` : le rôle de confiance de `raw_html_role`, vérifié par le vérificateur d'autorisation de Symfony |
| `Markup\RawHtmlAuthorPass` | Passe de compilation : avec une `RawHtmlPermission`, l'enveloppe dans `AuthorRawHtml` pour `BodyDataMapper` et `WorkingCopies`, et dit à `UjoBodyConverter` que le HTML brut est configuré |

### `Infrastructure\Twig`

| Classe | Rôle |
|---|---|
| `ContentExtension` | Déclare les sept fonctions Twig |
| `ContentRuntime` | Les implémente, construit à la demande |

### `Infrastructure\Console`

| Classe | Rôle |
|---|---|
| `ContentCommand` | Base abstraite : options de données, `DomainError` sous forme de message d'erreur |
| `CreateContentCommand`, `EditContentCommand`, `PublishContentCommand`, `UnpublishContentCommand`, `ListContentCommand` | `phil:content:create`, `edit`, `publish`, `unpublish`, `list` |
| `SubmittedFields` | Lit `--file`, `--field` et `--json` |

`ContentManager::removeDraft()` et `ContentManager::delete()` n'ont pas de commande de console ; on les appelle depuis
PHP ou, pour le premier, depuis l'administration.

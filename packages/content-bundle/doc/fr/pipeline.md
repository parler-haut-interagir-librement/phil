<!-- phil-dev source-sha256=8e13886361120dca1cc6a0adb3c0da95236626d669c6fa72d1a91e25c8b4a996 -->
# Le pipeline : mappeurs de données, fusionneurs, normaliseurs

Trois étapes étiquetées lisent et écrivent les capacités d'une ligne. Chaque étape est un service par capacité, et un
service collecteur les exécute toutes.

| Étape | Interface | Étiquette | Exécutée par | Ce qu'elle fait |
|---|---|---|---|---|
| 1 | `Application\Pipeline\DataMapper` | `phil_content.data_mapper` | `ContentDataMapper` | écrit les données soumises sur les lignes de brouillon non localisée et localisée |
| 2 | `Application\Pipeline\Merger` | `phil_content.merger` | `ContentAggregator`, `DimensionContentCopier` | copie une capacité d'une ligne enregistrée vers une ligne fusionnée ou copiée |
| 3 | `Application\Pipeline\Normalizer` | `phil_content.normalizer` | `ContentNormalizer` | transforme une capacité d'une ligne fusionnée en données simples |

Le bundle étiquette par configuration automatique chaque service qui implémente ces interfaces, et étiquette ses
propres services de la même façon : les étapes de l'application s'exécutent donc à côté de celles du bundle. Les noms
des étiquettes sont les constantes `PhilContentBundle::DATA_MAPPER_TAG`, `MERGER_TAG` et `NORMALIZER_TAG`.

```text
submitted data ──DataMapper──▶ draft rows (*, fr) ──Merger──▶ merged row ──Normalizer──▶ array
                                                    (aggregate)
```

## Les mappeurs de données

```php
interface DataMapper
{
    /** @param array<string, mixed> $data the submitted values, already validated by the application */
    public function map(DimensionContent $unlocalized, DimensionContent $localized, array $data): void;
}
```

`ContentDataMapper::map($unlocalized, $localized, $data)` exécute chaque mappeur de données sur les deux lignes de
brouillon de la dimension modifiée. L'appelant charge ou crée les lignes et les persiste ensuite ; `ContentEditor` fait
les deux ([publication.md](publication.md)). Chaque mappeur traite une capacité, ignore les lignes qui ne l'ont pas, et
décide lesquelles de ses valeurs sont partagées et lesquelles sont propres à chaque locale. L'ordre des mappeurs
n'importe pas, puisque deux mappeurs n'écrivent jamais les mêmes valeurs. **Une valeur refusée par un mappeur arrête
toute l'écriture** avant que quoi que ce soit soit persisté.

### Données soumises

Les clés sont celles de la forme normalisée. **Une clé absente ne change rien ; `null` efface sa valeur**, et une
chaîne vide une fois rognée compte comme `null`. C'est ce qui permet à un formulaire de soumettre seulement les champs
qu'il montre. Les chaînes sont rognées, sauf `bodySource`. Une valeur du mauvais type est refusée par
`InvalidContent`, qui nomme sa clé.

| Clé | Acceptée | Écrite sur | Par |
|---|---|---|---|
| `templateKey` | une chaîne ou `null` | ligne non localisée | `TemplateDataMapper` |
| `templateData` | une table de noms de champs (chaînes) vers des valeurs, ou `null` pour aucun champ ; remplacée en entier | ligne localisée | `TemplateDataMapper` |
| `seoTitle`, `seoDescription` | une chaîne ou `null` | ligne localisée | `SeoDataMapper` |
| `seoKeywords` | une liste de chaînes, une chaîne séparée par des virgules, ou `null` (la liste vide) ; éléments rognés, éléments vides écartés | ligne localisée | `SeoDataMapper` |
| `seoCanonicalUrl` | une chaîne (une URL absolue `http`/`https`) ou `null` | ligne localisée | `SeoDataMapper` |
| `seoNoIndex`, `seoNoFollow`, `seoHideInSitemap` | un booléen ou `null` | ligne localisée | `SeoDataMapper` |
| `excerptTitle`, `excerptDescription` | une chaîne ou `null` | ligne localisée | `ExcerptDataMapper` |
| `excerptImage` | une chaîne ou `null` | ligne non localisée | `ExcerptDataMapper` |
| `link` | `{url}`, `{resourceKey, resourceId, locale?}` ou `null` ; un tableau qui nomme les deux genres, ou aucun, est refusé | ligne localisée | `LinkDataMapper` |
| `slug` | une chaîne ou `null` | ligne localisée | `RoutableDataMapper` |
| `bodySource` | une chaîne, gardée telle qu'elle est écrite, ou `null` ; une valeur vide efface le corps | ligne localisée | `BodyDataMapper` |
| `bodyFormat` | `markdown` (en son absence), `html`, `bbcode`, `mediawiki`, `dokuwiki`, `creole`, `text` ou `ujo`, les valeurs du `MarkupFormat` de `phil/markup`, ou le nom d'un format qu'un module complémentaire apporte au compilateur ; lue seulement avec `bodySource` ; un format que le compilateur ne connaît pas, et une source UJO qui n'est pas un document valide, sont refusés | ligne localisée | `BodyDataMapper` |

Il n'y a pas de mappeur de données pour `WorkflowCapable` : la place et la date de publication sont écrites par le
workflow et le publieur, jamais par des données soumises.

`SubmittedData` contient les lecteurs qu'utilisent les mappeurs de données — `has()`, `string()`,
`untrimmedString()`, `bool()`, `stringList()`, `map()` — et un mappeur de données de l'application peut s'en servir
pour suivre les mêmes règles.

## Les fusionneurs

```php
interface Merger
{
    public function merge(DimensionContent $target, DimensionContent $source): void;
}
```

`ContentAggregator` demande une ligne neuve à la racine (`createDimensionContent()`), puis exécute chaque fusionneur
sur la ligne non localisée d'abord et sur la ligne localisée ensuite : **une valeur définie par la ligne localisée
l'emporte** sur la valeur partagée. **La fusion n'écrit jamais sur une ligne enregistrée.** Un fusionneur copie
seulement ce que sa source définit réellement, et ignore les lignes qui n'ont pas sa capacité.

| Fusionneur | Règle |
|---|---|
| `TemplateMerger` | la clé quand la source en définit une ; les valeurs des champs par-dessus celles de la cible, clé par clé (`array_replace`, superficiel) |
| `WorkflowMerger` | la place et la date de publication, chacune quand elle est définie |
| `SeoMerger` | chaque valeur quand elle est définie : un texte ou une URL quand ils ne valent pas `null`, les mots-clés quand ils ne sont pas vides, une directive quand elle vaut `true` ou `false` |
| `ExcerptMerger` | titre, description et image, chacun quand il est défini |
| `LinkMerger` | le lien, en entier, quand il est défini |
| `RoutableMerger` | le slug, quand il est défini |
| `BodyMerger` | le corps, en entier, quand il est défini |

`DimensionContentCopier::copy(DimensionContent $source, DimensionKey $dimension)` utilise les mêmes fusionneurs pour
copier une ligne vers une ligne neuve d'une autre dimension : la publication copie une ligne de brouillon vers sa
jumelle en ligne, la création de version copie une version vers la suivante. Une ligne neuve n'a aucune valeur : chaque
capacité qui a un fusionneur est donc copiée en entier. **Une capacité sans fusionneur n'est pas copiée**, et n'est pas
lue non plus par l'agrégateur.

## Les normaliseurs

```php
interface Normalizer
{
    /** @return array<string, mixed> */
    public function normalize(DimensionContent $content): array;
}
```

`ContentNormalizer::normalize($row)` exécute chaque normaliseur sur une ligne fusionnée et combine leurs fragments clé
par clé. **Deux normaliseurs qui écrivent la même clé sont refusés** par une `LogicException` plutôt que départagés par
l'ordre : le second cacherait le premier sans bruit, et celui qui l'emporte dépendrait de l'ordre des services dans le
conteneur. Un normaliseur renvoie un tableau vide pour une ligne qui n'a pas sa capacité.

### La forme normalisée

| Clé | Valeur | Source |
|---|---|---|
| `templateKey` | chaîne ou `null` | `TemplateNormalizer` |
| `templateData` | les valeurs des champs telles qu'elles sont enregistrées | `TemplateNormalizer` |
| `workflowPlace` | `unpublished`, `draft`, `published` ou `null` | `WorkflowNormalizer` |
| `workflowPublished` | chaîne RFC 3339 avec son décalage, ou `null` | `WorkflowNormalizer` |
| `seoTitle`, `seoDescription` | chaîne ou `null` | `SeoNormalizer` |
| `seoKeywords` | liste de chaînes | `SeoNormalizer` |
| `seoCanonicalUrl` | chaîne ou `null` | `SeoNormalizer` |
| `seoNoIndex`, `seoNoFollow`, `seoHideInSitemap` | booléen, `false` quand aucune ligne ne l'a défini | `SeoNormalizer` |
| `excerptTitle`, `excerptDescription` | chaîne ou `null` | `ExcerptNormalizer` |
| `excerptImage` | la référence telle qu'elle est enregistrée, ou `null` | `ExcerptNormalizer` |
| `link` | `null`, ou `{resourceKey, resourceId, locale, url}` avec les clés de l'autre genre à `null` | `LinkNormalizer` |
| `slug` | chaîne ou `null` | `RoutableNormalizer` |
| `bodySource` | chaîne ou `null` : le texte de l'auteur sur une ligne de brouillon, le JSON UJO sur une ligne en ligne | `BodyNormalizer` |
| `bodyFormat` | une valeur de `MarkupFormat` sur une ligne de brouillon, `ujo` sur une ligne en ligne, ou `null` | `BodyNormalizer` |
| `bodyHtml` | le HTML enregistré **purifié de nouveau à cette lecture**, ou `null` | `BodyNormalizer` |
| `bodyRawHtml` | si l'auteur du corps avait la permission d'écrire du HTML brut, ou `null` sans corps ; aucun *data mapper* ne le relit | `BodyNormalizer` |

La forme normalisée est ce que reçoit un gabarit sous le nom `content`, ce que renvoient `ContentManager::draft()` et
`live()`, et la forme que relisent les mappeurs de données : un `link` normalisé peut être soumis tel quel.

## Exemple : lire un brouillon à la main

```php
use Phil\ContentBundle\Domain\Dimension\{DimensionKey, Stage};
use Phil\LocaleDimensions\{Locale, Version};

$row = $aggregator->aggregate($root, DimensionKey::localized(Locale::fromString('fr'), Stage::Draft, Version::first()));
$data = $normalizer->normalize($row);   // ['excerptTitle' => '…', 'bodyHtml' => '…', …]
```

`ContentManager::draft()` et `live()` le font pour vous, en choisissant la bonne version
([content-manager.md](content-manager.md)).

<!-- phil-dev source-sha256=2e7023c863a2137fac53ba369833afcc938aac05c00621762e2f81db9bbfc30e -->
# La médiathèque

`MediaLibrary` est le service qu'appelle une application ; `MediaRepository` enregistre ce qu'il change.

## Téléverser

```php
$media = $library->upload($path, 'Été 2026.png', $collection);   // collection optional
```

Le type de média est lu dans le contenu du fichier, pas dans son nom ; les dimensions d'une image sont lues aussi. Le
fichier est écrit sous `originals/{id}/{version}/{safe name}` et le média est enregistré. Un fichier qui ne peut pas
être lu est refusé avec `InvalidMedia`.

Une image SVG est d'abord purifiée, quand `phil/svg-sanitizer` est installé et que `svg.purify` est actif, comme par
défaut : `SanitizingSvgPurifier` retire ce qui pourrait exécuter, charger ou intégrer quoi que ce soit (scripts,
gestionnaires d'événements, `foreignObject`, références externes, CSS que sa politique refuse), puis `SvgMinifier`
retire les commentaires, les instructions de traitement, `metadata` et les blancs entre les éléments, et arrondit les
coordonnées de `d`, `points` et `transform` à `svg.precision` décimales, 3 par défaut. Ce qui est gardé est l'image
purifiée, et sa taille la taille purifiée ; une image que le purificateur refuse en entier (mal formée, pas du SVG,
déclarant des entités, trop grande) est refusée avec `InvalidMedia`. Le texte de `text`, `tspan`, `textPath`, `title`,
`desc` et `style`, et tout ce qui est sous `xml:space="preserve"`, garde ses blancs.

Avec un format d'aperçu, chaque version d'une image matricielle reçoit aussi son aperçu minuscule : voir
[Images adaptatives](images.md#aperçus).

## Remplacer

```php
$library->replace($media, $path, 'Été 2026, recadrée.png');
```

Le média garde son identifiant : chaque page qui le montre montre donc le nouveau fichier. Sa version augmente de un et
le fichier précédent est gardé, enregistré comme `MediaVersion` ; `MediaRepository::versions($media)` les liste, la plus
récente d'abord. Les URL portent la version : les URL de l'ancien fichier cessent donc de répondre et les navigateurs
récupèrent le nouveau ; les formats rendus à partir de l'ancien fichier sont supprimés.

## Décrire et attribuer une licence

```php
$media->describe('La fête', alt: 'Des gens qui dansent sous des lanternes', credit: 'Ada');
$media->license('CC-BY-4.0', AiDisclosure::Assisted);
$repository->save($media);
```

| Champ | Règle |
|---|---|
| Titre, crédit | Jusqu'à 255 caractères ; vide signifie aucun |
| Texte de remplacement | Jusqu'à 500 caractères ; vide signifie aucun |
| Licence | Un identifiant SPDX (`CC-BY-4.0`, `CC0-1.0`) ou `all-rights-reserved`, la valeur par défaut |
| Divulgation de l'IA | `None`, fait par des personnes ; `Assisted`, fait avec l'aide d'un outil génératif ; `Generated` |

La divulgation de l'IA est ce qu'on dit aux personnes qui voient le média ; la montrer est le choix de l'application.

Avec `phil/creation-disclosure` installé, un média déclare comment il a été fait par un type de source numérique de
l'IPTC, plus précis que les trois valeurs d'`AiDisclosure` :

```php
use Phil\CreationDisclosure\DigitalSourceType;

$media->disclose(DigitalSourceType::CompositeWithTrainedAlgorithmicMedia);
$media->digitalSourceType();   // 'compositeWithTrainedAlgorithmicMedia'
$media->aiDisclosure();        // AiDisclosure::Assisted
```

La divulgation de l'IA suit le type : `Generated` pour un média créé par l'IA générative, `Assisted` pour un média
retouché par elle ou qui peut contenir des éléments générés, `None` sinon. Un type retiré est déclaré comme son
remplaçant. Le code est gardé dans la colonne `digital_source_type` et donné à schema.org comme `digitalSourceType` ;
`license()` avec une divulgation de l'IA l'oublie.

## Collections

```php
$events = MediaCollection::create('events', 'Événements');
$repository->save($events);
$media->moveTo($events);
$repository->inCollection($events, limit: 50, offset: 0);   // newest first; null, the media in none
```

Supprimer une collection laisse ses médias hors de toute collection.

## Lire plusieurs médias

```php
$found = $repository->getMany(...$ids);   // array<string, Media>, by identifier, in the order asked
```

`MediaRepository::getMany()` lit plusieurs médias en une requête : une
galerie, les émojis image d'une page. Un identifiant demandé deux fois est lu une fois, celui qui ne nomme rien est
laissé de côté. La requête lit au plus `DoctrineMediaRepository::BATCH` identifiants, 500, et une requête de plus lit
chaque tranche de 500 au-delà, pour que le nombre de paramètres liés reste sous les limites de chaque base de données.

## Supprimer

```php
$library->delete($media);
```

Le média, ses versions, ses fichiers originaux et ses formats rendus disparaissent. Le mettre plutôt dans la corbeille
de `phil/trash-bundle` est le choix de l'application. Un enregistrement qui montre encore le média, dans une colonne de
type `phil_media`, décide d'abord : [Enregistrements qui montrent des médias](doctrine.md) décrit `restrict`,
`set_null` et `keep`, et `MediaLibrary::usages($media)` liste où un média est montré, colonne par colonne.

## Renommer et déplacer

```php
$media->rename('Été 2026, recadrée.png');   // the name shown and saved; the file and its key stay
$media->moveTo($collection);               // or null, outside every collection
$repository->save($media);
```

`MediaRepository::collections()` liste chaque collection par nom. L'[explorateur de fichiers](admin.md) fait la même
chose depuis une page.

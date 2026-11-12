<!-- phil-dev source-sha256=fc1d813107f7e5c8f2f162e6c2aed68a2330744d207f09519165cb52278906c8 -->
# Enregistrements qui montrent des médias

## Le type de colonne

```php
use Phil\MediaBundle\Domain\ValueObject\MediaId;

#[ORM\Column(type: 'phil_media', nullable: true)]
private ?MediaId $cover = null;
```

Avec DoctrineBundle, le bundle enregistre le type de colonne `phil_media`, `MediaType` : une colonne fixe de
36 caractères qui contient l'identifiant d'un média, relu comme un `MediaId`. Un `Media` est accepté à l'écriture. Lire
un `MediaId` ne charge rien : les composants `Img` et `Picture`, `MediaRepository::get()` et la contrainte `MediaFile`
trouvent le média quand ils en ont besoin.

## La contrainte

```php
use Phil\MediaBundle\Infrastructure\Validator\MediaFile;

#[MediaFile(collections: ['covers', 'covers-*'], mimeTypes: ['image/*'])]
private ?MediaId $cover = null;
```

Avec `symfony/validator`, `MediaFile` vérifie que le média existe, qu'il est gardé dans l'une des collections et que
son fichier est de l'un des types de médias ; chaque liste accepte des motifs `*`, sans tenir compte de la casse, et une
liste vide permet tout. Un média hors de toute collection passe quand `collections` est vide ou contient `''`. La valeur
peut être un `Media`, un `MediaId` ou le texte de l'identifiant ; `null` et `''` passent, comme avec toute contrainte
sauf `NotNull`.

| Violation | Code | Message |
|---|---|---|
| Aucun média de ce nom | `phil_media.missing` | `missingMessage` |
| Hors des collections | `phil_media.collection` | `collectionMessage`, avec `{{ collections }}` |
| Un autre type de média | `phil_media.type` | `typeMessage`, avec `{{ type }}` et `{{ types }}` |

## Ce que fait une suppression

Une colonne `phil_media` n'a pas de clé étrangère : le média vit dans une autre table, et peut-être une autre base de
données. Avant de supprimer un média, `MediaLibrary::delete()` demande à `MediaReferences` quels enregistrements le
montrent encore. `DoctrineMediaReferences` trouve chaque colonne `phil_media` des entités mappées (y compris celles d'un
objet intégrable, `Entity.embedded.field`) et compte, colonne par colonne, les enregistrements qui contiennent le média.
Chaque colonne applique ensuite son comportement :

| Comportement | Les enregistrements qui montrent le média |
|---|---|
| `restrict` | Refusent la suppression : `MediaInUse`, qui liste chaque colonne et son compte dans `places` |
| `set_null` | Le perdent : la colonne est vidée dans chaque enregistrement, puis le média est supprimé |
| `keep` | Gardent son identifiant : le média est supprimé, les composants n'affichent rien pour lui |

```yaml
phil_media:
    delete_behaviour:
        default: restrict
        fields:
            'App\Entity\Post.cover': set_null
            'App\Entity\Event.poster': keep
```

`set_null` sur une colonne qui ne peut pas être nulle équivaut à `restrict`. Les comportements sont d'abord vérifiés,
puis appliqués ensemble : une seule colonne `restrict` qui contient le média refuse toute la suppression, et rien n'est
vidé. Le vidage se fait par un `UPDATE` DQL, qui contourne les événements de cycle de vie des entités.

`MediaLibrary::usages($media)` liste les mêmes `MediaReference` (entité, champ, compte, comportement) pour une page qui
montre où un média est utilisé. Un service `MediaReferences` propre à l'application, aliasé, remplace celui de
Doctrine.

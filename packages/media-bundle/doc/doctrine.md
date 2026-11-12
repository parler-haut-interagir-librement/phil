# Records that show media

## The column type

```php
use Phil\MediaBundle\Domain\ValueObject\MediaId;

#[ORM\Column(type: 'phil_media', nullable: true)]
private ?MediaId $cover = null;
```

With DoctrineBundle, the bundle registers the column type `phil_media`, `MediaType`: a fixed 36-character column that
holds a medium's identifier, read back as a `MediaId`. A `Media` is accepted when written. Reading a `MediaId` loads
nothing: the `Img` and `Picture` components, `MediaRepository::get()` and the `MediaFile` constraint find the medium
when they need it.

## The constraint

```php
use Phil\MediaBundle\Infrastructure\Validator\MediaFile;

#[MediaFile(collections: ['covers', 'covers-*'], mimeTypes: ['image/*'])]
private ?MediaId $cover = null;
```

With `symfony/validator`, `MediaFile` checks that the medium exists, that it is kept in one of the collections and
that its file is of one of the media types; each list takes `*` patterns, case aside, and an empty list allows any.
A medium outside every collection passes when `collections` is empty or holds `''`. The value may be a `Media`, a
`MediaId` or the identifier's text; `null` and `''` pass, as with every constraint but `NotNull`.

| Violation | Code | Message |
|---|---|---|
| No such medium | `phil_media.missing` | `missingMessage` |
| Outside the collections | `phil_media.collection` | `collectionMessage`, with `{{ collections }}` |
| Another media type | `phil_media.type` | `typeMessage`, with `{{ type }}` and `{{ types }}` |

## What a deletion does

A `phil_media` column has no foreign key: the medium lives in another table, and perhaps another database. Before it
deletes a medium, `MediaLibrary::delete()` asks `MediaReferences` which records still show it. `DoctrineMediaReferences`
finds every `phil_media` column of the mapped entities — those of an embeddable included, `Entity.embedded.field` —
and counts, column by column, the records that hold the medium. Each column then applies its behaviour:

| Behaviour | The records that show the medium |
|---|---|
| `restrict` | Refuse the deletion: `MediaInUse`, which lists each column and its count in `places` |
| `set_null` | Lose it: the column is emptied in every record, then the medium is deleted |
| `keep` | Keep its identifier: the medium is deleted, the components print nothing for it |

```yaml
phil_media:
    delete_behaviour:
        default: restrict
        fields:
            'App\Entity\Post.cover': set_null
            'App\Entity\Event.poster': keep
```

`set_null` on a column that cannot be null is `restrict`. The behaviours are checked first and applied together: a
single `restrict` column that holds the medium refuses the whole deletion, and nothing is emptied. The emptying runs
as a DQL `UPDATE`, which bypasses the entities' lifecycle events.

`MediaLibrary::usages($media)` lists the same `MediaReference`s — entity, field, count, behaviour — for a page that
shows where a medium is used. A `MediaReferences` service of the application's own, aliased, replaces Doctrine's.

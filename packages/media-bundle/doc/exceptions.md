# Exceptions

Everything the bundle throws implements `Phil\MediaBundle\Domain\Exception\MediaException`, which extends
`Throwable`: an application catches the bundle's exceptions in one place.

| Exception | Extends | Thrown when |
|---|---|---|
| `MediaInUse` | `RuntimeException` | `MediaLibrary::delete()` meets a record that shows the medium in a column that restricts; `places` lists each column and its count, `App\Entity\Post.cover (3)` |
| `InvalidMedia` | `InvalidArgumentException` | A value the bundle refuses — a file name, a media type, a license, a focal point, a collection key, a format, a transformation, a storage key —; a file that cannot be read or decoded; an image larger than `max_pixels`; a format, a width or a type an image is not rendered at; a storage or an encoder that fails |

`MediaInUse::by(string $id, list<string> $places)` names the medium and the columns that still show it; the file
explorer reports it beside the medium. `InvalidMedia::because(string $reason, ?Throwable $previous = null)` carries the
reason as its message, and the failure of the layer below — Flysystem's `FilesystemException` — as its previous
exception. The messages are written for developers and logs, not for visitors: `MediaController` turns an `InvalidMedia`
met while rendering into a `404`.

# Integrations

## Image uploads

The application keeps images; the bundle carries them from the editor to it. Write an `ImageUploader`:

```php
use Phil\BlockEditorBundle\Application\Exception\UploadRefused;
use Phil\BlockEditorBundle\Application\Upload\ImageUpload;
use Phil\BlockEditorBundle\Application\Upload\ImageUploader;
use Phil\BlockEditorBundle\Application\Upload\UploadedImage;

final readonly class MediaUploader implements ImageUploader
{
    public function __construct(private MediaLibrary $library, private Urls $urls) {}

    public function upload(ImageUpload $image): UploadedImage
    {
        $type = mime_content_type($image->path);

        if (!\in_array($type, ['image/jpeg', 'image/png', 'image/webp', 'image/avif'], true)) {
            throw new UploadRefused('Only JPEG, PNG, WebP and AVIF images are accepted.');
        }

        $media = $this->library->upload($image->path, $image->clientName);

        return new UploadedImage($this->urls->of($media), $media->width, $media->height, ['media' => $media->id]);
    }
}
```

```yaml
phil_block_editor:
    image_uploader: App\Media\MediaUploader
```

`ImageUpload` carries the temporary file and what the browser said of it — name, type, size —, which is never proof:
the uploader reads the file itself. `UploadRefused`'s message is shown to the writer. `UploadedImage` becomes the
block's `file`: `url`, `width` and `height` when known, and the extra fields the application keeps — an identifier
of its media library, a credit. Over `phil/media-bundle`, the uploader calls its library and returns the medium's
signed URL and identifier.

The endpoint takes the `image` field of a multipart `POST`, refuses a file above `max_upload_bytes`, and answers as
the Editor.js image tool expects:

```json
{"success": 1, "file": {"url": "/media/7/cat.webp", "width": 1200, "height": 800, "media": 7}}
```

## Mentions and page links

Write a `ReferenceSearch` per menu:

```php
use Phil\BlockEditorBundle\Application\Reference\ReferenceSearch;
use Phil\BlockEditorBundle\Application\Reference\Suggestion;

final readonly class PeopleSearch implements ReferenceSearch
{
    public function __construct(private People $people) {}

    public function search(string $query, int $limit): iterable
    {
        foreach ($this->people->startingWith($query, $limit) as $person) {
            yield new Suggestion('user:'.$person->id, $person->name, '/people/'.$person->slug, $person->handle);
        }
    }
}
```

```yaml
phil_block_editor:
    mention_search: App\Search\PeopleSearch
    link_search: App\Search\PageSearch
```

A suggestion's reference is `kind:identifier` — a lower-case kind, then up to 128 letters, digits, `_`, `.`, `/` or
`-` —, the form `phil/ujo` keeps in `data-mention` and `data-ref`; its label is what the menu shows and the text
inserts. The search receives what was typed after the trigger, trimmed and cut to 100 characters, possibly empty, and
the endpoint answers with its first 8 suggestions:

```json
{"items": [{"reference": "user:42", "label": "Ada Lovelace", "url": "/people/ada", "hint": "@ada"}]}
```

Searches run under the writer's session: a search returns only what that person may see.

## Guarding the endpoints

Every endpoint first asks the application's authorization checker for the `access` attribute, `IS_AUTHENTICATED` by
default, and refuses whoever is not granted it, before calling the uploader or the search; without SecurityBundle it
refuses everyone. A firewall does not do this on its own: a lazy one, or one that lets anonymous visitors through,
reaches the routes for anyone who sends the headers below from a script. With `access: ~`, the application must guard
the prefix with an `access_control` entry ([installation.md](installation.md#the-routes)).

The `ImageUploader` and the `ReferenceSearch` services are not given the user: when the files kept, or the
suggestions returned, depend on who asks, read the current user from Symfony's `Security` service there:

```php
public function search(string $query, int $limit): iterable
{
    $member = $this->security->getUser();

    if (null === $member) {
        return [];
    }

    // only what $member may see
}
```

Every endpoint also requires, checked by `EditorRequest`, so that another site cannot call it in the writer's name:

- the editor's own header, `X-Phil-Block-Editor: 1`, which a form or a link of another site cannot add without the
  browser asking first;
- no `Sec-Fetch-Site: cross-site` from the browser;
- for the image upload, a `POST`, Symfony's stateless CSRF check of the token `phil_block_editor`; the `GET` searches
  of the `@` and `[[` menus change nothing and carry no token. For each upload the editor draws a new random
  token, sets it in a cookie named after the value the field gives it (`csrf-token` by default, FrameworkBundle's
  `csrf_protection.cookie_name`; `__Host-` first and `secure` over HTTPS, same-site only) and sends it in a header
  of the same name. `SameOriginCsrfTokenManager` accepts the request when the browser's `Sec-Fetch-Site`, `Origin`
  or `Referer` names this site, or when the header and the cookie carry the same token, and refuses it when either
  says otherwise; FrameworkBundle clears the cookie on the answer.

A refused request answers 403, a route without its service 404.

## Knowing where things are used

`DocumentReferences` reads a document for what it points to:

```php
use Phil\BlockEditorBundle\Application\Reference\DocumentReferences;

DocumentReferences::mentions($document);   // ['user:42', …]
DocumentReferences::links($document);      // ['page:7', …]
DocumentReferences::files($document);      // ['/media/7/cat.webp', …] — images, attachments, galleries
```

With `phil/references-bundle`, a reference collector of the application's own turns these into links, so that a page
linked to, or an image shown, cannot be deleted while a document still uses it. The documents are read in the
canonical form `phil/ujo` writes: decode or normalize them first.

## Rendering for readers

The editor does not render documents for readers. `phil/ujo-bundle`'s `ujo_html` does, with the classes the editor's
inline tools write; the site's stylesheet styles them, and gives each colour token its colour:

```css
.text-color[data-color='brand'] { color: var(--brand); }
```

# The file explorer

## Routes

```yaml
# config/routes/phil_media_admin.yaml
phil_media_admin:
    resource: '@PhilMediaBundle/config/admin_routes.php'
    prefix: /admin/media
```

The application puts the prefix behind its firewall. The explorer asks the application's security for `attribute`,
`ROLE_ADMIN` by default, before every listing, the picker included, and every change: on the collection listed or
uploaded into, on the medium renamed, moved or deleted and on the collection it is moved to (`null`, the root, when none), on nothing for a new collection. A role, or the attribute of
a voter that decides by the subject, opens it; anything else answers `403`, and so does every request when Symfony's
security is not installed.

```yaml
phil_media:
    admin:
        attribute: MEDIA_MANAGE   # a voter's attribute; ROLE_ADMIN by default
```

| Route | Method and path | |
|---|---|---|
| `phil_media_admin` | `GET /` | The listing: `collection`, `view` (`grid` or `list`), `page`; `picker=1` for the picker |
| `phil_media_admin_upload` | `POST /upload` | `files[]`, several at once, into `collection` |
| `phil_media_admin_collection` | `POST /collections` | A collection: `key` and `name` |
| `phil_media_admin_rename` | `POST /{id}/rename` | `filename` |
| `phil_media_admin_move` | `POST /{id}/move` | `collection`, or `''` for none |
| `phil_media_admin_delete` | `POST /{id}/delete` | The medium, as `MediaLibrary::delete()` |

## The listing

`MediaAdminController` lists the media of a collection, or of none, newest first, `page_size` at a time, as a grid of
thumbnails or a list; each medium has its name, its media type, its size in pixels and in kibibytes, and its forms to
rename, move and delete it. An image's thumbnail is the format `admin_thumbnail`, 240 × 240 fitted, which the bundle
declares unless the application declares a format of that name; another file shows its extension.

Every form works without JavaScript: it posts, and the browser is sent back with a `303` to the listing, which says
what was done or what was refused — a file too large, a name refused, a collection that exists, a medium in use.
Asked for JSON, the same routes answer JSON, `422` on a refusal.

A form posted from another site is refused with a `403`. `ExplorerAccess` asks for two things:

- the browser's `Sec-Fetch-Site` says `same-origin`, or, when it sends none, its `Origin` is this site's;
- the form passes Symfony's stateless CSRF check of the token `phil_media_explorer`. Each form carries the field
  `_csrf_token`, rendered with the name of the token's cookie (`csrf-token` by default, FrameworkBundle's
  `csrf_protection.cookie_name`). As a form is sent, the `explorer` and `upload` controllers replace it by a new
  random token and set it in a cookie of that name (`__Host-` first and `secure` over HTTPS, same-site only), so that
  the field and the cookie carry the same token; without JavaScript the field keeps the name and the origin alone
  decides. `SameOriginCsrfTokenManager` refuses a token that is neither, and a cookie of another token when the
  browser's headers do not name this site; FrameworkBundle clears the cookie on the answer.

The bundle adds the token to FrameworkBundle's `csrf_protection.stateless_token_ids`, which turns
`framework.csrf_protection` on: leave it on.
With `csrf_protection: false`, the container stops with a `LogicException` that says so.

```yaml
phil_media:
    admin:
        layout: '@PhilMedia/admin/layout.html.twig'   # a template with a "content" block
        page_size: 48
        max_upload_bytes: 33554432                      # 32 MiB; PHP's upload_max_filesize must allow it
        attribute: ROLE_ADMIN                           # the role or voter attribute that opens the explorer
```

The layout is the application's: its stylesheets and the Stimulus application go there. The bundle's templates are
plain HTML with `phil-media-*` classes, and the application may override any of them under
`templates/bundles/PhilMediaBundle/`.

## Stimulus

With AssetMapper, the bundle maps its controllers under `@phil/media-bundle`; StimulusBundle registers them from
`assets/package.json`:

| Controller | Identifier | What it does |
|---|---|---|
| `explorer` | `phil--media-bundle--explorer` | Switches between grid and list without reloading, remembers the choice in the browser, asks before a deletion, gives each form a new CSRF token as it is sent |
| `upload` | `phil--media-bundle--upload` | Sends each chosen or dropped file in its own request with a new CSRF token, with a progress bar, refuses a file too large before sending it, then reloads the listing |
| `picker` | `phil--media-bundle--picker` | Opens the picker in a `<dialog>`, follows its collections and pages inside it, and fills the field with the chosen medium |
| `placeholder` | `phil--media-bundle--placeholder` | Paints a medium's tiny preview behind its image while it loads, for the `Img` and `Picture` components — see [Responsive images](images.md#placeholders) |

## The picker

```php
use Phil\MediaBundle\Infrastructure\Form\MediaPickerType;

$builder->add('cover', MediaPickerType::class, ['collection' => 'covers']);
```

With `symfony/form`, `MediaPickerType` is a hidden field holding the medium's identifier, its data a `MediaId`, shown
with the chosen file's name and thumbnail, a button that opens the picker — the listing without the layout, each
medium a button —, and one that empties the field. `collection` opens the picker on a collection; a value that is no
identifier is a transformation failure, `This is not a medium.`. The bundle adds its form theme,
`@PhilMedia/form/media_picker.html.twig`, to Twig's. Pair the field with the `MediaFile` constraint to limit what may
be chosen: the picker shows every collection.

# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

The library declares no service and takes no port. What an application relies on:

| To | Rely on |
|---|---|
| Announce a page that goes online or offline | `new PagePublished(...)` and `new PageRetired(...)`, dispatched on the application's dispatcher by the package that owns the page |
| Follow both alike | `Contract\PageEvent`: `canonicalUrl`, `locale`, `indexable` and `ownerId`, read-only |
| Follow one | A listener of `PagePublished` or of `PageRetired`, by class |
| Refuse a malformed event | `Exception\InvalidPageEvent`, thrown by both constructors |

`PageEventFields` holds the checks both events share; it is internal and may change in any version.

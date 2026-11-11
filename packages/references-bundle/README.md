# phil/references-bundle

**Know where each resource is used, and refuse to delete one that something still needs.**

phil/references-bundle tells a Symfony application where its resources are used. A `ReferenceCollector` the
application writes finds the links of one kind of source, such as an image in a page's JSON blocks or a polymorphic
association. The bundle keeps these links in a table, up to date at each Doctrine flush. Deleting a resource still
used rolls the flush back with a `StillReferenced` that names the sources using it. `phil:references:check` lists the
links to resources that are gone, and fails while there is one.

It covers the links a foreign key cannot hold: a reference inside a JSON column, or a kind and an identifier side by
side. It is for an application whose editors build pages out of blocks and media, and need to see where an image
appears before they remove it. Calling `References::assertUnused()` before a deletion gives the person a clear
message; the flush listener is the safety net behind it.

The bundle stores its table through Doctrine DBAL and listens to Doctrine ORM flushes. Resources are named through
`phil/resource`, so sources and targets of any kind share one table. The design rests on two ports the application
fills: collectors say what a source links to, and a `ReferenceTarget` says whether a resource of its kind still
exists. A kind without a target is never refused a deletion nor checked for broken links.

## Status

| Part | State |
|---|---|
| `Usage`, the repository port; `ResourceReference` of `phil/resource` | Implemented and tested |
| `References`: refresh, forget, usages, deletion check, broken links; `ReferenceCollector`, `ReferenceTarget`, `Link` | Implemented and tested |
| `DbalUsageRepository`, its table and schema listener | Implemented and tested |
| `ReferenceTrackingListener`: usages at each flush, deletion refused | Implemented and tested |
| `phil:references:check` | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, registering, configuration, the table. |
| [Collectors and targets](doc/collectors.md) | Writing a collector and a target; what the flush does. |
| [Usages](doc/usages.md) | "Where is it used", refusing a deletion, broken links. |
| [Extension points](doc/extension-points.md) | What an application implements, configures, replaces or relies on. |
| [Testing](doc/testing.md) | How the bundle is tested. |
| [Reference](doc/reference.md) | Every type of the bundle. |

## Quick start

```php
$references->usagesOf(ResourceReference::of('image', $image->id));   // where is it used?
$references->assertUnused(ResourceReference::of('image', $image->id)); // StillReferenced when it is
```

```bash
bin/console phil:references:check
```

## Requirements

PHP 8.5 or later; Doctrine DBAL 4.5 and ORM 3.7; Symfony 8.2's dependency injection and config; `phil/resource` 0.1
for resource references. Symfony Console is optional.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).

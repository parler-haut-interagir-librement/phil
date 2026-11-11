# Extension points

What an application implements, configures or relies on. References are `phil/resource`'s.

## Ports

### `ReferenceCollector`

`Phil\ReferencesBundle\Application\ReferenceCollector` — finds what one kind of source links to. A service
implementing it is tagged `phil_references.collector` by autoconfiguration. See
[Collectors and targets](collectors.md).

| Method | Contract |
|---|---|
| `supports(object $source): bool` | Whether it reads this source |
| `sourceOf(object $source): ResourceReference` | The source's own reference, read before a deletion empties its identifier |
| `collect(object $source): iterable<Link>` | Each link, with the field it is in — a property, or a path into a JSON document |

### `ReferenceTarget`

`Phil\ReferencesBundle\Application\ReferenceTarget` — one kind of resource others use. A service implementing it is
tagged `phil_references.target` by autoconfiguration. Without one, a kind is never refused a deletion nor checked
for broken links.

| Method | Contract |
|---|---|
| `kind(): string` | The kind it knows |
| `exists(string $id): bool` | Whether that resource still exists |
| `referenceOf(object $resource): ?ResourceReference` | The reference of an object of its kind, `null` for another object |

### `UsageRepository`

`Phil\ReferencesBundle\Domain\Repository\UsageRepository` — the table of usages, a public alias of
`DbalUsageRepository` on `connection`. A store of the application's own keeps `replace()` writing only what changed
and `usagesOfAll()` reading many targets at once.

## Configuration

| Option | What it extends |
|---|---|
| `connection` | The Doctrine connection that keeps the usages, and whose entities are tracked |
| `track_entities` | Whether the usages of Doctrine entities are kept up to date at each flush |
| `block_deletion` | Whether deleting an entity still used aborts the flush |

## Services

`References` — `usagesOf()`, `assertUnused()`, `broken()`, `refresh()`, `forget()` — is what the application calls:
before a deletion, to show a message rather than a rolled-back flush, and for sources that are not Doctrine entities.
See [Usages](usages.md).

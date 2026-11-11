# Doctrine

The bundle registers the column type `phil_content_warnings` when DoctrineBundle is enabled. It keeps a
`ContentWarnings` in one JSON column, as `ContentWarnings::toArray()` writes it, and works on any entity:

```php
use Phil\ContentWarnings\ContentWarnings;

#[ORM\Column(type: 'phil_content_warnings', nullable: true)]
private ?ContentWarnings $warnings = null;
```

```json
[{"theme":"gore","severity":"severe","note":"The final battle"},{"theme":"spiders"}]
```

`null` is stored as SQL `NULL`, and no warnings as `[]`. A value that is neither null nor `ContentWarnings` is refused
with Doctrine's `InvalidType`. A stored value that is not JSON, nests deeper than a warning, or is anything
`ContentWarnings::fromArray()` refuses throws Doctrine's `ValueNotConvertible`, the cause attached.

Warnings name themes by key: a theme removed from the catalogue stays in the column, and display falls back to its key.

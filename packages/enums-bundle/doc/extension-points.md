# Extension points

The bundle has no port to implement. What an application provides is declarative:

- **On an enum**: the metadata of `phil/enums` — `#[Label]`, `#[Description]`, `#[Group]`, its own meta
  properties —, `AsTranslatableEnumTrait` with `implements TranslatableInterface` to make it translatable, and
  `#[Translatable(domain:, prefix:)]` to choose its translation domain and key prefix.
- **In translation files**: the translations of the labels and descriptions, keyed by the source texts, in the
  enum's domain ([Translation](translation.md)).
- **On a form field**: the options `group`, `only`, `except` and `where_meta` ([Forms](forms.md)).
- **On a property**: the `EnumCase` constraint ([Validation](validation.md)).
- **On a case, for badges**: the `Color` and `Icon` meta properties, as names the application's stylesheet and
  icon set know ([Twig](twig.md)).
- **In templates**: `templates/bundles/PhilEnumsBundle/badge.html.twig`, which replaces the bundle's badge.
- **In the configuration**: the paths the commands read, and the `EnumSet` column types ([Configuration](configuration.md)).

The services the bundle registers, with their tags, are listed in [Configuration](configuration.md). An
application that wants another label rule can still pass its own `choice_label` to a field: the extension only sets
the default.

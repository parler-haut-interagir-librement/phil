# Configuration

```yaml
# config/packages/phil_content_warnings.yaml
phil_content_warnings:
    base_catalogue: true
    catalogue_files:
        - '%kernel.project_dir%/config/content_warnings.php'
    catalogue:
        categories:
            venues: { label: { en: Venues, fr: Lieux } }
        themes:
            crowds: { category: venues, label: { en: Crowds, fr: Foules } }
        remove: [clowns]
    default_display: fold
    fallback_locales: [en]
    preferences_store: ~
```

| Key | Default | Meaning |
|---|---|---|
| `base_catalogue` | `true` | Starts from the library's base list; `false` starts from an empty catalogue |
| `catalogue_files` | `[]` | PHP or JSON files of catalogue definitions, applied in order after the base list |
| `catalogue` | `{}` | A definition applied last |
| `default_display` | `fold` | What happens to warned content for a reader who chose nothing: `show`, `fold` or `hide` |
| `fallback_locales` | `[en]` | The locales a label is read in when it has none in the current locale, in order |
| `preferences_store` | `~` | The service id of the `WarningPreferencesStore`; the session store by default |

A definition has the keys `replace`, `categories`, `themes`, `remove` and `remove_categories`, as
`phil/content-warnings` describes them in its catalogue documentation. Parameters in file paths are resolved.

The catalogue is built once when the container is compiled: an unknown theme to remove, a theme without category,
a malformed key or a missing file throws `InvalidCatalogue` and stops the build, so a broken catalogue never reaches a
request. At run time the service `Phil\ContentWarnings\Catalogue` is built once per container and is immutable, safe
in a worker.

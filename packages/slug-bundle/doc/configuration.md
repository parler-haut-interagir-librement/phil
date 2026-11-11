# Configuration

```yaml
# config/packages/phil_slug.yaml
phil_slug:
    default: default            # the slugger used when none is named
    sluggers:
        default: ~
        titles:
            locale: fr          # the transliteration of a locale; null for the generic one
            max_length: 80      # the longest slug, cut at the last hyphen that fits (1 to 255)
        directory:
            leading_words: [le, la, les, l, the]
    history:
        enabled: false          # the history of former slugs, see history.md
        connection: default     # its DBAL connection
        clock: clock            # its PSR-20 clock
```

| Key | Default | Rule |
|---|---|---|
| `default` | `default` | The name of one of the `sluggers`; not empty |
| `sluggers` | one slugger named after `default` | By name: a lowercase letter, then lowercase letters, digits or `_` (`-` is read as `_`) |
| `sluggers.<name>.locale` | `null` | Any locale `symfony/string` transliterates in |
| `sluggers.<name>.max_length` | `255` | 1 to 255 |
| `history.enabled` | `false` | `true` registers the history of former slugs; needs `doctrine/dbal` |
| `history.connection` | `default` | A DBAL connection name; not empty |
| `history.clock` | `clock` | A PSR-20 clock service id; not empty |
| `sluggers.<name>.leading_words` | `[]` | Words dropped once from the start of a slug when others follow, spelled as slug words: each one run of lowercase ASCII letters and digits |

- A `default` that names none of the `sluggers` given is refused, with the names there are.
- A leading word that is not one slug word — `Le`, `l'`, `de-la` — is refused when the container is built, by the rule
  of `LeadingWordsRemoved::checkWord()`, rather than on the first request that builds the slugger.
- With no `sluggers`, the bundle registers one, named after `default`, with every option at its default.
- A slugger with `leading_words` is a `LeadingWordsRemoved` around a `TransliteratingSlugger`; one without is the
  `TransliteratingSlugger` alone.
- With `history.enabled` and no `doctrine/dbal`, the container build fails and says which package to require. See
  [History of former slugs](history.md).
- **No slugger drops words unless the configuration says so**: the articles of one language, a site's own prefixes,
  are each application's choice.

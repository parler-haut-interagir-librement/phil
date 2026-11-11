# Exceptions

The bundle has no exception of its own.

| When | What is thrown |
|---|---|
| The container is built with a fixer name the presets do not know in `locales` | `Symfony\Component\Config\Definition\Exception\InvalidConfigurationException`, naming the allowed values |
| A typographer is built for a string that is not a locale — `default_locale`, a key of `locales`, the locale given to a filter | `Phil\Typography\Exception\InvalidLocale` |
| A locale is configured with `hyphen` and the library has no patterns for its language | `Phil\Typography\Exception\UnsupportedLanguage` |

Each typographer is built on first use, so the last two are thrown where it is first asked for: a service, a filter,
the rendering of a UJO document. Fixing text never throws.

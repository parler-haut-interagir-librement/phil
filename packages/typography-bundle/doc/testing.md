# Testing

## The bundle's tests

`tests/Unit` checks the configuration and the services it gives, the decoration of the UJO renderer, the filters and
the UJO filter, in a given locale and in the current one. `tests/Functional` boots a small application —
FrameworkBundle in French with its translator, TwigBundle, `phil/ujo-bundle` and this bundle — and renders a template
with both filters and a UJO document, in the default locale and in another one set by `LocaleSwitcher`.

From the monorepo root:

```sh
vendor/bin/phpunit packages/typography-bundle/tests
```

The bundle class is held to 100 % covered mutation score by Infection, like the library.

## Testing an application

A functional test asserts on the rendered HTML. Disable hyphenation in the test configuration unless the test checks
it, so soft hyphens do not appear in every expected string:

```yaml
# config/packages/test/phil_typography.yaml
phil_typography:
    hyphenation: false
```

The no-break space appears as `&nbsp;` in HTML; the narrow no-break space (U+202F) and the soft hyphen (U+00AD)
appear as themselves, best written `\u{202F}` and `\u{AD}` in PHP strings.

# Testing

| Area | What is covered |
|---|---|
| `Locale` | The spellings accepted and refused, the language and region, the language tag, shared languages, equality |
| `Version` | The first version, the next, a stored number read back, zero and negative numbers refused, order and equality |
| `FallbackChain` | The requested locale, resolution in chain order, a chain that runs out, a change of language, the refusals |
| `DeclaredLocales` | Declaration order, the chains, served and unserved locales, every malformed declaration |

The suite runs without a kernel, a database or a network. From the monorepo root:

```sh
vendor/bin/phpunit packages/locale-dimensions/tests
```

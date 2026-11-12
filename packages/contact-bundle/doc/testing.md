# Testing

| Area | What is covered |
|---|---|
| `ContactCardType` and its fields | Submitting with collections, empty entries and cards, showing, options, bad entries, the structured name |
| `PersonNameType` | Submitting the parts, the culture option and field, the order, showing, empty and bad names |
| `ContactCardColumnType` | JSON, nesting, refusals, structured names, cards stored without one |
| `VCardResponse`, `JSContactResponse`, `ContactExtension`, `PhilContactBundle` | Headers and file names, the functions, the prepended configuration, the JSContact reader and its bounds |
| The Stimulus controller | Indexes, placeholders, removing, events, under Node's test runner |

A functional suite boots a kernel with FrameworkBundle, DoctrineBundle on SQLite, TwigBundle, the phone, URL and
messaging bundles and this one: `ContactBundleTest` stores a card with its structured name, renders the form in its
theme in `fr_CA`, with and without the parts of the name, serves and reads a card as JSContact, and renders the
functions. Installed on its own, where FrameworkBundle is a development requirement, the suite is skipped.

```sh
vendor/bin/phpunit packages/contact-bundle/tests
node --import ./tests/js/register.mjs --test tests/js   # from packages/contact-bundle
```

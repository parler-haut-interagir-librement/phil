# Testing

## The bundle's tests

The suite `tests/Unit` tests the rows; `Translations` on the stores in memory — preparing, proposing exact matches,
bringing segments up to date, every action and who it is signed by, learning, matches, terms, issues, checks and
flags, progress, export, import, machine translation, credits, regional variants and both readings of the translated
document —; `Glossaries` with an inflector and TBX, `Comments` and `Progress`; one contract for both kinds of stores,
run on the stores in memory and on Doctrine with SQLite in memory; the controllers with the bundle's Twig templates —
every answer, every action, comments, machine translation, inherited targets, the glossary pages, forms from another
site, without their CSRF token or with a wrong one, uploads —; the credits in Twig and the EasyAdmin links; and the
configuration and wiring. The suite `tests/Functional` boots a small application with FrameworkBundle, DoctrineBundle
on SQLite and TwigBundle, translates a page through the routes, refused without a token, accepted with a token and its
cookie, edits a glossary and comments a segment. `tests/js`
runs the module of the double-submitted token under Node's test runner.

From the monorepo root, then from the bundle:

```sh
vendor/bin/phpunit packages/translation-bundle/tests
node --test tests/js     # from packages/translation-bundle
```

The bundle is held to 100 % covered mutation score by Infection.

## Testing an application that uses it

Set `store: memory` in the test environment to keep segments, terms and the memory in arrays, and call `Translations`
from the container:

```yaml
# config/packages/test/phil_translation.yaml
phil_translation:
    store: memory
```

```php
$translations = self::getContainer()->get(Translations::class);
$translations->prepare('article:42', $content, LanguageTag::of('en'), LanguageTag::of('fr'));
$translations->translate('article:42', LanguageTag::of('fr'), 'b1/text', 'Bonjour.');
```

To test the screen, post its forms with the header `Sec-Fetch-Site: same-origin` and the field `_csrf_token` set to
the name of the token's cookie, `csrf-token` by default, as a browser without JavaScript does; or with a token of 24
characters or more in the field and the cookie `csrf-token_<token>` set to `csrf-token`, as the `csrf` controller
does.

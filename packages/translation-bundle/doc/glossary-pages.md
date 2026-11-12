# Glossary pages

The glossary of a pair of languages is edited on screen: a table of its terms, a form that adds or changes a term, a
button that removes one, the export of the glossary as TBX and the import of a TBX file. Every change goes through the
bundle's `Glossaries`, as the application's own code would.

## Routes

| Route | Method and path | Does |
|---|---|---|
| `phil_translation_glossary` | `GET /glossary/{source}/{target}` | The terms and the form |
| `phil_translation_glossary_save` | `POST /glossary/{source}/{target}` | `action=save` adds or changes a term, `action=remove` removes one, then back with a `303` |
| `phil_translation_glossary_export` | `GET /glossary/{source}/{target}/tbx` | The glossary as a TBX attachment |
| `phil_translation_glossary_import` | `POST /glossary/{source}/{target}/tbx` | A TBX file put into the glossary, then back with the count |

The routes come with the screen's, from `config/routes.php`, after them: a document named `glossary` keeps its screen.

## Who may edit

`TranslationPolicy::canEditGlossary($source, $target)` answers for the current user. Without a `policy`, the pages
answer 404; for a user it refuses, or the same language twice, 403; for a language that is not a tag, 404. Forms from
another site are refused as on the screen.

## The form

| Field | Meaning |
|---|---|
| `term` | The term in the source language |
| `forms` | Its inflected forms, one per line |
| `translations` | Its translations, one per line; none keeps the term as it is |
| `translation_forms` | The inflected forms of the translations, one per line |
| `forbidden` | Refused translations, one per line |
| `case_sensitive` | Whether case matters |
| `note` | A note for translators |

A term without words, or longer than 255 bytes, is refused with `422` and the reason.

## TBX

`Glossaries::export()` writes the terms as they are kept — without the forms an inflector adds — as TBX-Basic, and
`Glossaries::import()` puts the terms of a TBX file of 2019 or 2008 into the glossary: new terms added, terms it has
replaced. A file larger than 10 MiB, unreadable, or with a term longer than 255 bytes is refused with `422`.

## EasyAdmin

An EasyAdmin back office links to the pages, which keep their own routes, forms and layout — the bundle's `layout`,
pointed at a template of the back office:

```php
use Phil\TranslationBundle\Infrastructure\EasyAdmin\TranslationAdmin;

public function configureMenuItems(): iterable
{
    yield from TranslationAdmin::menuItems([['en', 'fr'], ['en', 'fr-CA']]);
}
```

`menuItems()` gives one link per pair of languages, labelled `Glossary en → fr` unless a label with two `%s` is given,
with the icon `fa fa-language` unless another is. EasyAdmin is suggested, not required.

## Inflected forms

With an inflector, the glossary that finds and checks terms adds the forms it makes:

```yaml
phil_translation:
    inflector: Phil\TranslationMemory\Glossary\RegularPlurals   # the regular plurals of English and French
```

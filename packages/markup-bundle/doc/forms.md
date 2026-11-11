# Forms

`Phil\MarkupBundle\Infrastructure\Form\MarkupFormatType` is a choice of the format a text is written in, registered
when Symfony Form is installed. It offers the enabled formats, in the order of `formats` in the
[configuration](configuration.md), and its model is the name of the format, a string: `markdown`, or the name of a
format an add-on brings.

```php
$builder
    ->add('format', MarkupFormatType::class)
    ->add('source', TextareaType::class);
```

A value that is not an enabled format is not synchronized, and the field shows the form's `invalid_message`. The type
is a child of Symfony's `ChoiceType`: its options — `expanded`, `placeholder`, `preferred_choices`, `choices` to offer
fewer formats — apply as they do there.

## Labels

| Format | Label | French |
|---|---|---|
| `markdown` | Markdown | Markdown |
| `html` | HTML | HTML |
| `bbcode` | BBCode | BBCode |
| `mediawiki` | MediaWiki | MediaWiki |
| `dokuwiki` | DokuWiki | DokuWiki |
| `creole` | Creole | Creole |
| `text` | Plain text | Texte brut |
| `ujo` | Block editor (UJO) | Éditeur par blocs (UJO) |

The labels are translated in the `phil_markup` domain, from the bundle's `translations/` directory; an application
overrides one in its own `translations/phil_markup.<locale>.*` file. A format an add-on brings shows the `label` of its
entry in `added_formats`, translated in the same domain from the add-on's own files; its name, without one.

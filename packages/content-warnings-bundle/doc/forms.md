# Forms

## Picking warnings

`ContentWarningsType` picks the warnings of a piece of content among the themes of the catalogue. Its data is a
`ContentWarnings`, or `null` when nothing is checked.

```php
$builder->add('warnings', ContentWarningsType::class);
$builder->add('warnings', ContentWarningsType::class, ['expanded' => false, 'locale' => 'fr']);
```

| Option | Default | Meaning |
|---|---|---|
| `expanded` | `true` | Checkboxes; `false` gives a multiple select with an option group per category |
| `locale` | the current locale | The locale of the labels |
| `label` | `form.warnings` | Translated in the `phil_content_warnings` domain |

The field is always multiple. Its choices are the themes grouped by category, labelled in the locale and the fallback
locales. A warning kept keeps its severity and its note, and a warning whose theme the catalogue no longer has is kept
as it is: the field only adds and removes the warnings of the themes it lists. A severity or a note is set by the
application's own fields or code, with `ContentWarnings::with()`.

The form theme `@PhilContentWarnings/form/theme.html.twig`, added to Twig's form themes, renders the checkboxes of
each category in a `fieldset` whose `legend` is the category's label.

## Choosing preferences

`WarningPreferencesType` is the form where a reader chooses what happens to warned content. Its data is a
`WarningPreferences`.

```php
$form = $this->createForm(WarningPreferencesType::class, $store->current());
// on a valid submission
$store->save($form->getData());
```

| Child | What it holds |
|---|---|
| `default` | The display of every warning no other choice covers |
| `categories` | A display per category, empty to follow the default |
| `themes` | A compound per category, with a display per theme, empty to follow its category |

| Option | Default | Meaning |
|---|---|---|
| `themes` | `true` | `false` leaves out the per-theme choices |
| `locale` | the current locale | The locale of the category and theme labels |

The displays are labelled `display.show`, `display.fold` and `display.hide`, and the empty choice
`preferences.inherit`, in the `phil_content_warnings` domain. A preference whose display is kept keeps the severity it
applied from; the preferences of themes and categories the form does not list are kept as they are.

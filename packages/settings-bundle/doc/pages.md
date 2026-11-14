# The settings pages

## Preferences and the site's settings

The bundle has two pages, one section at a time, each a form generated from the section's settings:

| Route | Shows | To |
|---|---|---|
| `phil_settings_preferences` | The users' settings of the section | The current user; a visitor, the settings kept for visitors |
| `phil_settings_site` | The site's settings of the section | The users of each setting's role, `site_role` by default |

Without a section, a page redirects to its first. A section it does not show is a `404`; a page with nothing to show is
an access denied, which sends a visitor to the sign-in page. A valid form saves, then redirects to its section with
`?saved=1`, so that reloading saves nothing twice; a refused value shows the form again with its error in a `422`.
Pages are sent with `Cache-Control: no-store, private`.

## The preview

Beside its save button, the form has a preview button, named `phil_settings_preview`. A valid form submitted by it
saves nothing and sets no cookie: the page comes back, in a `200`, with the form as submitted and, for each setting of
the section, its value now and the value saving would give it, the settings that would change marked. The values read
as people choose them — a choice by its label, a boolean as yes or no, an empty text or list said as such. A refused
value shows its error as a save would, in a `422`. Saving then is pressing the save button of the same form.

`Settings::preview()` gives the same comparison to an application's own page, as a list of `SettingPreview`.

## The form

Each setting is a field named after its key, the dots as hyphens, labelled by its translation keys: a checkbox, a
number field with its bounds, a text field with its length, radio buttons or checkboxes. The fields carry no class of
their own, so the classless stylesheet of `phil/ux-css` dresses them. A refused value is an error on its field, written
in the reader's language from the `phil_settings` domain, in English and French.

The form is named `phil_settings`, which is the identifier of its CSRF token. The bundle adds it to the stateless
tokens of FrameworkBundle: the protection checks the origin of the request and needs no session, so a visitor can save
their display preferences without one. The container refuses to build when forms are used without their CSRF
protection.

## The template

`@PhilSettings/settings.html.twig` is a page of its own. An application gives its own in `template`, extending its
layout and including the part the bundle renders:

```twig
{# templates/settings.html.twig #}
{% extends 'base.html.twig' %}

{% block title %}{{ ('phil_settings.title.' ~ scope)|trans({}, 'phil_settings') }}{% endblock %}

{% block body %}
    {{ include('@PhilSettings/_settings.html.twig') }}
{% endblock %}
```

| Variable | Holds |
|---|---|
| `form` | The form's view |
| `sections` | Each section shown: `key`, `label`, `translation_domain`, `url`, `current` |
| `section` | The current `SectionDefinition` |
| `scope` | `user` or `site` |
| `saved` | Whether the last change was saved |
| `preview` | The settings of the section before a save, empty unless previewed: `key`, `label`, `translation_domain`, `current`, `proposed` and `changed`, a value being parts, each a `message` with its translation `domain`, or a text when the domain is null |

The part renders the sections as a `nav` whose current link has `aria-current="page"`, the section's title and help,
the status once saved, the preview as a table, and the form with its two buttons.

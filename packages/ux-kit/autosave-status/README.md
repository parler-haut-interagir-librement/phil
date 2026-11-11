# AutosaveStatus

The status of a form's draft, for the autosave controller of `phil/forms-autosave`: a polite live region the
controller writes what it did in — saved, restored, discarded —, and the notice of a restored draft or of a newer one
saved from another device or tab, hidden until then, with its buttons. It is written inside a form whose tag carries
the controller, which the option `autosave: true` of the form gives.

```twig {"preview":true}
<twig:AutosaveStatus />
```

## Installation

::: installation

## Usage

```twig
{{ form_start(form) }}
    <twig:AutosaveStatus />
    {{ form_widget(form) }}
{{ form_end(form) }}
```

## Examples

### Translated buttons

The buttons are props; the controller's messages come from the bundle's labels.

```twig
<twig:AutosaveStatus
    :discardLabel="'phil_forms_autosave.discard'|trans({}, 'phil_forms_autosave')"
    :dismissLabel="'phil_forms_autosave.dismiss'|trans({}, 'phil_forms_autosave')"
    :useServerLabel="'phil_forms_autosave.useServer'|trans({}, 'phil_forms_autosave')"
/>
```

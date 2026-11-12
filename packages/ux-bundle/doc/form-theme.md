# The form theme

`@PhilUx/form/phil_ux_layout.html.twig` is Symfony's div layout with its rows written as the fields of the PHIL UX
kit, the classes phil/ux-css dresses. It takes from `form_div_layout.html.twig` every block it does not write.

## Applied to every form

The bundle prepends the theme to `twig.form_themes`, as Symfony does for its default theme. The list Symfony reads is
`form_div_layout.html.twig`, then the PHIL UX theme, then the themes the application lists: a later theme wins, so a
block the application writes replaces the bundle's. A `{% form_theme %}` tag in a template wins over all of them, and
`{% form_theme form with [...] only %}` leaves them out.

```yaml
# config/packages/phil_ux.yaml
phil_ux:
    form_theme: false    # the forms keep the themes of twig.form_themes only
```

Turn the theme off when the application uses another complete theme, such as `bootstrap_5_layout.html.twig`: mixed
blocks of two themes give markup neither expects.

## The markup

| Block | What the theme writes |
|---|---|
| `form_row` | `<div class="isField" data-slot="field">`, the label with `isFieldLabel`, the errors, the widget and the help |
| `form_row` of a compound field | `<fieldset class="isFieldSet" data-slot="field-set">` and its `<legend class="isFieldLegend">`: expanded choices, a date of three lists, a sub-form |
| `form_row` with errors | `isDanger` and `data-invalid="true"` on the row; Symfony marks the control `aria-invalid="true"` |
| `form_help` | `<div id="…_help" class="isFieldDescription" data-slot="field-description">` |
| `form_errors` | `<div class="isFieldError" data-slot="field-error">` holding a `p` for one error, a `ul` for several, each with the id Symfony names in `aria-describedby` |
| `choice_widget_expanded` | `isRadioGroup` on the radio buttons of a single choice; check boxes keep the layout of phil/ux-css |

Every other block is Symfony's. The classes the application gives in `row_attr`, `label_attr`, `help_attr` and `attr`
are kept after the theme's own.

```html
<div class="isField isDanger" data-slot="field" data-invalid="true">
    <label class="isFieldLabel required" for="event_title">Title</label>
    <div class="isFieldError" data-slot="field-error"><p id="event_title_error1">This value is too short.</p></div>
    <input type="text" id="event_title" name="event[title]" required="required"
        aria-describedby="event_title_error1" aria-invalid="true">
</div>
```

## Accessibility

- The label names the control by `for`; a compound field is a `fieldset` named by its `legend`, so each radio button
  is read with the question it answers.
- The help and the errors are tied to the control by `aria-describedby`, which Symfony writes, and the control is
  marked `aria-invalid`.
- The errors arrive with the page, so they are not a live region: one would read every error of the form at once
  when the page loads.
- An invalid row does not rely on colour alone: the message is text, and phil/ux-css doubles the danger border.

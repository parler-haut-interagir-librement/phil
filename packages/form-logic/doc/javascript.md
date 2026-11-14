# JavaScript

## The controller

`assets/src/logic_controller.js`, registered as `phil--form-logic--logic`, runs on the form's tag. Its one value,
`steps`, is the logic as `FormLogic::toArray()` writes it.

```twig
<form data-controller="phil--form-logic--logic"
      data-phil--form-logic--logic-steps-value="{{ logic.toArray()|json_encode }}">
    <div data-form-logic-field="guest_name">
        <label for="guest_name">Guest</label>
        <input id="guest_name" name="entry[guest_name]">
    </div>
    <p>Seats: <output data-form-logic-output="seats"></output></p>
</form>
```

| Attribute | On | |
|---|---|---|
| `data-form-logic-field` | the wrapper of a field | The field whose condition shows or hides the wrapper |
| `data-form-logic-output` | any element | The computed variable whose value is its text, empty when `null` |

When it connects, and at each `input` or `change` in the form, the controller collects the answer, runs the steps as
the server's `Judge` does, and:

- sets `hidden` on the wrapper of each hidden field, and disables its controls, so that the browser does not send
  them; a control the page disabled stays disabled, and the controls it disabled are enabled again when the field is
  shown, their values read back;
- writes each computed variable into its outputs;
- dispatches `phil--form-logic--logic:change`, with `{hidden, computed}` in `detail`.

A rule it cannot read — outside the subset or malformed — leaves every field shown and every output empty. The server
reads the same rules strictly and judges the answer again, whatever the browser sent.

## The answer it collects

Each enabled control of the form answers under its name, `entry[guest_name]` and `guest_name[]` read as
`guest_name`; submit, button, reset and file controls are left out.

| Control | Value |
|---|---|
| A lone checkbox | `true` or `false` |
| Checkboxes of one name, or a name ending in `[]` | the list of the checked values |
| A multiple select | the list of the selected values |
| Radio buttons | the checked value, or `null` |
| Any other control | its value; an empty value is `null` |

## The evaluator

`assets/src/logic.js` holds the evaluator, rule for rule the same as the PHP classes `Evaluator` and `Values`:

| Export | |
|---|---|
| `check(rule)` | Refuses a rule outside the subset as a whole, unreached branches included, with `UnreadableRule` |
| `evaluate(rule, data)` | The value of a rule for this data |
| `holds(rule, data)` | Its truth |
| `judge(steps, answer)` | The hidden fields and the computed variables, as the server's `Judge` gives them |
| `truthy`, `toNumber`, `toText`, `looselyEqual`, `strictlyEqual`, `less`, `contains` | The conversions of [The rules](rules.md#the-conversions) |

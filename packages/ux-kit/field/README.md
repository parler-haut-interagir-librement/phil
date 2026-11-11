# Field

A form field: its label, its control, its help and its errors, together; and sets of fields under a legend.

```twig {"preview":true}
<twig:Field :invalid="true">
    <twig:Field:Label for="email">Email address</twig:Field:Label>
    <twig:Input id="email" type="email" name="email" aria-describedby="email-help email-error" aria-invalid="true" />
    <twig:Field:Description id="email-help">The address we write to.</twig:Field:Description>
    <twig:Field:Error id="email-error" :errors="['This address is already taken.']" />
</twig:Field>
```

## Installation

::: installation

## Usage

```twig
<twig:Field :invalid="true">
    <twig:Field:Label for="email">Email address</twig:Field:Label>
    <twig:Input id="email" type="email" name="email" aria-describedby="email-help email-error" aria-invalid="true" />
    <twig:Field:Description id="email-help">The address we write to.</twig:Field:Description>
    <twig:Field:Error id="email-error" :errors="['This address is already taken.']" />
</twig:Field>
```

## Accessibility

The label names the control through `for`; the description and the error are tied to it by `aria-describedby` and `aria-invalid`, which the application writes on the control. The error is a live alert, written only when there is one. A `Field:Set` is a `fieldset`, its `Field:Legend` the name of the group.

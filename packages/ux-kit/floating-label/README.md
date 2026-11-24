# FloatingLabel

A field whose label sits inside the empty control and moves above it once the control holds a value or has the focus.

```twig {"preview":true}
<twig:FloatingLabel label="Email" id="email" name="email" type="email" autocomplete="email" />
```

## Installation

::: installation

## Usage

```twig
<twig:FloatingLabel label="Email" id="email" name="email" type="email" autocomplete="email" :required="true" />
<twig:FloatingLabel label="Message" id="message" name="message" type="textarea" />
```

## Accessibility

The label is a `label` element tied to its control, never a placeholder: it stays shown, above the value once there is one, and a click on it gives the control the focus. It moves without motion where motion is reduced.

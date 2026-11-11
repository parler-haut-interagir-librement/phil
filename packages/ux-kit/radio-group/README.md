# RadioGroup

Radio buttons that share a name: the browser checks one at a time, moves between them with the arrows and submits it, without JavaScript.

```twig {"preview":true}
<twig:RadioGroup name="plan" value="pro" aria-label="Plan">
    <twig:RadioGroup:Item value="free" id="plan-free" /><twig:Label for="plan-free">Free</twig:Label>
    <twig:RadioGroup:Item value="pro" id="plan-pro" /><twig:Label for="plan-pro">Pro</twig:Label>
</twig:RadioGroup>
```

## Installation

::: installation

## Usage

```twig
<twig:RadioGroup name="plan" value="pro" aria-label="Plan">
    <twig:RadioGroup:Item value="free" id="plan-free" /><twig:Label for="plan-free">Free</twig:Label>
    <twig:RadioGroup:Item value="pro" id="plan-pro" /><twig:Label for="plan-pro">Pro</twig:Label>
</twig:RadioGroup>
```

## Accessibility

The group has the role `radiogroup`; name it with `aria-label` or `aria-labelledby`, and each radio button with a `Label`. One Tab reaches the group, the arrows move inside it.

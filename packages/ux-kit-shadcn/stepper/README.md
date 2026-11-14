# Stepper

The steps of a process, the finished ones marked, the current one announced, reading downwards on a phone.

```twig {"preview":true}
<twig:Stepper label="Checkout">
    <twig:Stepper:Item state="done" href="/cart" doneLabel="Finished:">Cart</twig:Stepper:Item>
    <twig:Stepper:Item state="current">Address</twig:Stepper:Item>
    <twig:Stepper:Item>Payment</twig:Stepper:Item>
</twig:Stepper>
```

## Installation

::: installation

## Usage

```twig
<twig:Stepper label="Checkout">
    <twig:Stepper:Item state="done" href="/cart" doneLabel="Finished:">Cart</twig:Stepper:Item>
    <twig:Stepper:Item state="current">Address</twig:Stepper:Item>
    <twig:Stepper:Item>Payment</twig:Stepper:Item>
</twig:Stepper>
```

## Accessibility

An ordered list, the current step with `aria-current="step"`. Say with `doneLabel` what the mark of a finished step shows.

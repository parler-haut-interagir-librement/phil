# Accordion

Sections that open and close, on `details` and `summary`: they work without JavaScript, the browser keeping one open at a time; the controller adds the arrows, Home and End.

```twig {"preview":true}
<twig:Accordion id="faq" defaultValue="price">
    <twig:Accordion:Item value="price">
        <twig:Accordion:Trigger>Is it free?</twig:Accordion:Trigger>
        <twig:Accordion:Content>Yes.</twig:Accordion:Content>
    </twig:Accordion:Item>
    <twig:Accordion:Item value="where">
        <twig:Accordion:Trigger>Where?</twig:Accordion:Trigger>
        <twig:Accordion:Content>In Montreal.</twig:Accordion:Content>
    </twig:Accordion:Item>
</twig:Accordion>
```

## Installation

::: installation

## Usage

```twig
<twig:Accordion id="faq" defaultValue="price">
    <twig:Accordion:Item value="price">
        <twig:Accordion:Trigger>Is it free?</twig:Accordion:Trigger>
        <twig:Accordion:Content>Yes.</twig:Accordion:Content>
    </twig:Accordion:Item>
    <twig:Accordion:Item value="where">
        <twig:Accordion:Trigger>Where?</twig:Accordion:Trigger>
        <twig:Accordion:Content>In Montreal.</twig:Accordion:Content>
    </twig:Accordion:Item>
</twig:Accordion>
```

## Accessibility

`summary` is focused and toggled by the keyboard natively. A disabled item is `inert`: it can neither be focused nor opened.

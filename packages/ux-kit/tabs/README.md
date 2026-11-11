# Tabs

Tabs over panels. Without JavaScript every panel shows; with it, one at a time, the arrows, Home and End moving between the tabs.

```twig {"preview":true}
<twig:Tabs defaultValue="month">
    <twig:Tabs:List aria-label="View">
        <twig:Tabs:Trigger value="month">Month</twig:Tabs:Trigger>
        <twig:Tabs:Trigger value="list">List</twig:Tabs:Trigger>
    </twig:Tabs:List>
    <twig:Tabs:Content value="month">The month.</twig:Tabs:Content>
    <twig:Tabs:Content value="list">The list.</twig:Tabs:Content>
</twig:Tabs>
```

## Installation

::: installation

## Usage

```twig
<twig:Tabs defaultValue="month">
    <twig:Tabs:List aria-label="View">
        <twig:Tabs:Trigger value="month">Month</twig:Tabs:Trigger>
        <twig:Tabs:Trigger value="list">List</twig:Tabs:Trigger>
    </twig:Tabs:List>
    <twig:Tabs:Content value="month">The month.</twig:Tabs:Content>
    <twig:Tabs:Content value="list">The list.</twig:Tabs:Content>
</twig:Tabs>
```

## Accessibility

The pattern of WAI-ARIA: `tablist`, `tab`, `tabpanel`, the selected tab alone in the tab order, the selection following the arrows. Name the list with `aria-label`.

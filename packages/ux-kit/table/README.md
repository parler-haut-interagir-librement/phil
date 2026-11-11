# Table

A table of data, which scrolls sideways inside its own box on a small screen.

```twig {"preview":true}
<twig:Table>
    <twig:Table:Caption>The next events</twig:Table:Caption>
    <twig:Table:Header><twig:Table:Row><twig:Table:Head>Name</twig:Table:Head></twig:Table:Row></twig:Table:Header>
    <twig:Table:Body><twig:Table:Row><twig:Table:Cell>Workshop</twig:Table:Cell></twig:Table:Row></twig:Table:Body>
</twig:Table>
```

## Installation

::: installation

## Usage

```twig
<twig:Table>
    <twig:Table:Caption>The next events</twig:Table:Caption>
    <twig:Table:Header><twig:Table:Row><twig:Table:Head>Name</twig:Table:Head></twig:Table:Row></twig:Table:Header>
    <twig:Table:Body><twig:Table:Row><twig:Table:Cell>Workshop</twig:Table:Cell></twig:Table:Row></twig:Table:Body>
</twig:Table>
```

## Accessibility

A real `table`, its caption the name; a header cell is `scope="col"` unless the attribute says `row`.

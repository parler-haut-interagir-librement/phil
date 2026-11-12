# Installation

## Requirements

- PHP 8.5 or later, with the `mbstring` extension.
- `phil/accessibility-check`, for the success criteria of WCAG 2.2, their levels and the reports an audit writes.
- `phil/url`, which reads the `https://` addresses a statement links to as a browser reads them.

## Installing

```sh
composer require phil/accessibility-statement
```

The library holds no service and reads no file: an application builds its statements, reads them from its own
storage with `StatementReader`, and generates the next version with `StatementGenerator`. In a Symfony application,
`phil/accessibility-statement-bundle` wires a page, a store and a command around it.

# Exceptions

Every exception the library throws implements `Phil\CommerceProductTemplate\Exception\ProductTemplateException`.

| Exception | Extends | Thrown when |
|---|---|---|
| `InvalidDefinition` | `InvalidArgumentException` | A part of a template or design is refused at construction, or read back from an array no code of the library wrote |
| `PlanRefused` | `DomainException` | A design cannot be planned on a template: see [Planning](planning.md) |

Messages name the code, value or path refused, for the person who maintains the templates.

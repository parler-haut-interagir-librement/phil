# Extension points

The library is plain PHP and declares no service. What an application adds is nodes, rules and types; what it may
implement is the collector the parts of a page pour their nodes into.

## `NodeCollector`

`Phil\StructuredData\Contract\NodeCollector` — where the parts of an application add the nodes of a page.

| Method | Contract |
|---|---|
| `add(Node ...$nodes): void` | Keeps the nodes for the graph of the page being built, merged by `@id` with the others |

A package that describes something on a page — a contact card, an event, a product — depends on this interface
alone, never on a framework: given a collector, it adds its node; given none, it may print its own script element. In
a Symfony application, phil/structured-data-bundle provides the collector of the current request.

## `TypeRules`

`Phil\StructuredData\Validation\TypeRules` — what a node of one type needs and should have, and its parent.

```php
$validator = new Validator()->withRules(
    new TypeRules('Recipe', required: ['name', 'recipeIngredient'], recommended: ['image', 'totalTime'], parent: 'CreativeWork'),
);
```

| Argument | Contract |
|---|---|
| `type` | The schema.org type the rules apply to |
| `required` | Properties, or lists of properties of which one is enough, whose lack is an error |
| `recommended` | The same, whose lack is a warning |
| `parent` | A type whose rules apply too; a loop of parents is followed once |

`new Validator($rules)` takes the whole set instead of `Rules::schemaOrg()`; `withRules()` adds rules to the current
set, replacing those of the same types.

## Other types

A type no factory builds is a `Thing::of('Recipe', …, properties: [...])` or a `Node::of('Recipe', [...])`. Its
properties are free; the validator checks the values of the properties it knows by name, whatever the type.

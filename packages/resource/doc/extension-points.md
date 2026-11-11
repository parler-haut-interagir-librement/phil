# Extension points

The library is one value type: it declares no service and no port. What is open is the naming of kinds.

| Point | How |
|---|---|
| The kinds of resources | Chosen by the application: any key of the rules, `article`, `media.image`, one per kind of thing it points at |
| How a reference is found from an object | The application's: each package that takes references — a trash, a usage index, a schedule — asks the application for the reference of an object through a port of its own |
| How a reference is kept | `toString()` gives `kind:identifier` for one column, `fromString()` reads it back; or two columns from `key` and `id` |

A kind is part of the data: renaming one means updating every row that names it, in every package that keeps
references.

# Extension points

What an application configures, decorates or relies on. The types are `phil/slug`'s.

## Configuration

| Option | What it extends |
|---|---|
| `sluggers` | One slugger per name, each with its `locale`, `max_length` and `leading_words` — see [Configuration](configuration.md) |
| `default` | Which named slugger `Phil\Slug\Slugger` is |
| `history` | The history of former slugs, its connection and its clock — see [History of former slugs](history.md) |

## Services

| Service | How an application relies on it |
|---|---|
| `phil_slug.slugger.<name>` | Decorated by the application, with `#[AsDecorator]` or `decorates:`: the decorator takes the place of the slugger in `Sluggers`, through the aliases and in Twig — see [Services](services.md#decorating-a-slugger) |
| `Phil\Slug\Slugger` | Autowired: the default slugger |
| `Phil\Slug\Slugger $<name>Slugger` | Autowired by argument name: the slugger of that name |
| `Phil\Slug\Sluggers` | Injected wherever the slugger is chosen at run time; public |
| `Phil\SlugBundle\Application\Contract\SlugHistory` | Autowired when the history is enabled: recorded by the code that changes a slug, read by a redirect provider or a controller |
| `phil_slug.history` | The Doctrine history; an application replaces it by aliasing `SlugHistory` to its own implementation |

A bundle that takes a slugger by service id is given `phil_slug.slugger.<name>`.

## SlugHistory

An implementation other than the Doctrine one keeps the contract's invariants:

- a scope and a subject are checked with `SlugHistoryKeys` before anything is stored or read;
- recording a change makes the new slug stop being a former slug of the scope, whichever subject had it, points every
  former slug of the subject to the new slug, and writes the old slug as a former slug of the subject, all at once;
- recording a slug as its own former slug changes nothing;
- `formerSlugs()` never yields a chain: each value is a current slug;
- it keeps nothing between two calls, or is tagged `kernel.reset`.

## Twig

The `phil_slug` filter takes a slugger's name; it uses the same services, decorators included.

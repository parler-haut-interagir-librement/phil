# Extension points

What an application or another bundle implements, configures, replaces or relies on. See
[Documents](documents.md) and [WebFinger](webfinger.md).

## Contracts

| Interface | How it is used |
|---|---|
| `Phil\WellKnownBundle\Application\Contract\WellKnownDocument` | A document of its own, served at its path: `#[AsWellKnownDocument('matrix/client')]` on the class with autoconfiguration, or the `phil_well_known.document` tag with `path` and an optional `route` |
| `…\Application\Contract\WebFingerResolver` | Adds resources and links to WebFinger; autoconfigured with the `phil_well_known.webfinger_resolver` tag, after the resources of the configuration |
| `…\Application\Contract\NodeInfoProvider` | Tells the NodeInfo software, usage, registrations and metadata; named in `nodeinfo.provider` |
| `…\Application\Contract\SecurityTxtSigner` | Signs the security.txt; named in `security_txt.signer` |

A document's `path` is a name under `/.well-known/` — segments of letters, digits, `.`, `_`, `~` and `-`, each
starting with a letter or a digit — or an absolute path for a document a well-known one points to. Its route is named
`phil_well_known_` and the path, its other characters as `_`, unless `route` names it. Two documents at one path or with
one route name stop the container from being built. The controller adds the headers of [Documents](documents.md#headers);
a document that wants another lifetime sets its own `max-age`, or `no-store`.

## Tags

| Tag | Attributes | Role |
|---|---|---|
| `phil_well_known.document` | `path`, `route` | A document and where it is served |
| `phil_well_known.webfinger_resolver` | `before`, `after` | A WebFinger resolver and its order |

## Configuration

| Option | What it extends |
|---|---|
| `cache_max_age` | How long successful answers may be cached |
| One section per document | Which documents are served, and what they hold — see [Configuration](configuration.md) |
| `documents` | Fixed documents at other well-known paths |

## Services

| Service | How an application relies on it |
|---|---|
| `Phil\WellKnownBundle\Infrastructure\Http\WellKnownController` | The controller of every route; public |
| `Phil\WellKnownBundle\Application\WebFinger` | With `webfinger`: injected to describe a resource in code |
| The `phil_well_known` route type and `config/routes.php` | Imported where the application wants the documents served — see [Installation](installation.md) |
| The `phil_well_known.routes` parameter | The path of each document, keyed by its route name |

# Checking a page

```bash
bin/console phil:structured-data:check https://example.com/jeux/tournoi
bin/console phil:structured-data:check var/pages/tournoi.html --strict
bin/console phil:structured-data:check https://example.com/ --format=json
```

The command reads every JSON-LD element of the page with `JsonLdReader`, merges them into one graph, and checks it with
the `Validator` of phil/structured-data.

| Argument or option | Meaning |
|---|---|
| `page` | An `http://` or `https://` address, fetched with `symfony/http-client`; or the path of a saved HTML page. An address of any other scheme — `file://`, `phar://` — is refused, never opened through a stream wrapper |
| `--strict` | Fail on warnings too |
| `--format` | `text`, the default, or `json` |

| Exit code | When |
|---|---|
| 0 | No error, and no warning with `--strict` |
| 1 | An error, an element that cannot be read, a warning with `--strict`, or a page that cannot be read |
| 2 | An unknown format |

The text lists each element that could not be read and each issue, then the count of nodes, elements, errors and
warnings. The JSON holds the `page`, the number of `elements` and `nodes`, the `unreadable` elements and the `issues`.

A page is fetched with a `GET`, five redirections at most, within `check.timeout` seconds; a page larger than 5 MB is
cut and refused, and an answer outside 2xx is a failure. A deployment can check a few addresses of each kind of page
after it goes live.

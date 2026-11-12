# Console

`Console\CheckCommand` is the command `phil:accessibility:check`. It checks a file, or the HTML given as the argument
itself; it never fetches an address.

```sh
bin/console phil:accessibility:check templates/legal.html
bin/console phil:accessibility:check var/export/article.json --format=json --locale=fr
bin/console phil:accessibility:check '<img src="cat.jpg">' --min-severity=warning
```

| Argument or option | Means |
|---|---|
| `file` | the file to check, or the HTML itself — or the JSON of a UJO document with `--ujo`; an address (`https://…`) is refused |
| `--ujo` | read the content as a UJO document; a file whose name ends with `.json` is one already |
| `--format` | `text`, one line per finding and a count, or `json`, the report |
| `--min-severity` | `error` by default, `warning` or `notice`: the severity from which a finding fails the check |
| `--locale` | the locale of the messages; the policy's by default |
| `--language` | the language of a content that does not say its own |

A file is read up to one byte more than the policy's `maxBytes`, so that a larger file is refused without being read
whole. A UJO document is read and normalized by `phil/ujo`.

| Exit code | When |
|---|---|
| 0 | no finding is the minimum severity or graver |
| 1 | a finding is |
| 2 | the content cannot be read, is refused by the checker, or is not a UJO document; an option has a wrong value |

The command takes the `AccessibilityChecker` it checks with: register it as a service with the application's checker
([Installation](installation.md#in-a-symfony-application)).

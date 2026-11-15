# Console

## phil:terms:outstanding

```sh
bin/console phil:terms:outstanding terms             # the members not in order with the policy now
bin/console phil:terms:outstanding terms --latest    # those who have not accepted its latest material version
bin/console phil:terms:outstanding terms --count     # their number only
```

The members are read from the member source a batch of 500 at a time and listed one identifier per line, as they are,
for another command to read. An undeclared policy ends with the status `2` (invalid).

## phil:terms:check-legal-links

```sh
bin/console phil:terms:check-legal-links
```

With `phil/legal-notices-bundle` enabled, checks that the legal links exist on the site, one line for each thing
missing:

| Code | What it means |
|---|---|
| `missing_route` | A route of the footer links is not imported |
| `missing_document` | The legal notice or the privacy policy has no version in force |
| `missing_policy` | A required policy is not in `phil_legal_notices.footer.terms_policies` |
| `not_in_layout` | `pages.layout` shows no link to that address |
| `unreadable_layout` | `pages.layout` cannot be rendered |

The expected links are those of `phil_legal_notices_links()`, the accessibility statement included when
`phil/accessibility-statement-bundle` adds it. The layout is rendered as a page would be, with a request for `/` and no
variable of its own. The command exits with `1` when anything is missing, so that a deployment stops on it.

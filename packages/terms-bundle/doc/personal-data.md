# Personal data

## What an acceptance keeps

| Column | What |
|---|---|
| `member` | The member's stable identifier, as `member_resolver` gives it — never an email address with the default resolver |
| `policy`, `version` | Which version was accepted |
| `accepted_at` | When, from the clock, kept in UTC |
| `channel` | `signup`, `form`, `api` or `import` |
| `network` | With `network` enabled, the network of the request's address; otherwise nothing |

The address itself is never kept. With `network` enabled, `phil/ip-anonymizer` truncates it to a /24 for IPv4 and a
/48 for IPv6 by default; a text that is not an address keeps nothing. An application that wants another treatment —
a keyed digest, a coarser network — implements `AddressMasker`.

Acceptances are only ever added: a later acceptance stands beside the earlier ones, which remain the record of what
was accepted when. The bundle never updates one, and removes them only for an erasure.

## Export and erasure

With `phil/privacy-bundle`, `AcceptancesPrivacy` is the module `terms` of an access or erasure request, for the member
the subject names: the export lists each acceptance — policy, version, moment, channel and network —, the erasure
deletes them all and reports the store `phil_terms_acceptance` as deleted. It runs again on an erased member with the
same report.

## Versions

The versions hold the texts of the policies and nothing about the members.

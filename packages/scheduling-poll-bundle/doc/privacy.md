# Privacy

## What is kept

| Table | What | How long |
|---|---|---|
| `phil_scheduling_poll` | The poll, its organizer as a reference | As long as the application keeps it |
| `phil_scheduling_poll_slot` | The dates | With the poll |
| `phil_scheduling_poll_respondent` | The name, the address if given, the account if any, the language, the time zone, the answers, and when | The name, the address and the time zone until `link_lifetime` after the closing; the rest with the poll |
| `phil_access_link_invitation`, `phil_access_link` | The invitation of each respondent, named by their identifier, and the keyed hashes of their tokens | As `phil/access-links` keeps them |

The address is asked only to send the date chosen, and the page says so. No address, name or token travels through
Messenger: the messages carry identifiers, and the worker reads the rest.

## The links

A token is 256 random bits, shown once and kept only as its HMAC-SHA-256 under the keys of `links.keys`: a copy of the
tables opens no link without the key. To rotate the key, put the new one first and keep the old one after it until the
links sent under it have expired, `link_lifetime` after their poll's deadline. The pages keep the token out of the
`Referer` header and out of caches.

## The erasure after the closing

The schedule sends `EraseRespondents` every `erasure.frequency`; its handler erases the name, the address and the time
zone of the respondents of the polls closed longer ago than `link_lifetime`, each once. The answers stay, without anyone
behind them: the table of the answers then writes a number instead of the name.

## The export and the erasure of a person

When `phil/privacy-bundle` is installed, `SchedulingPollPrivacy` takes part in its export and its erasure, as the module
`scheduling_poll`. The data subject is either an account — a reference of the kind `privacy.account_kind`, `user` by
default, its identifier the subject — or, for a person without an account, their address, as the privacy bundle names
a guest by default.

- The export lists every respondent of the person: the poll and its title, the name, the address, the account, the
  language, the time zone, the answers, and when they joined, answered or were revoked.
- The erasure deletes those respondents, reported as `Deleted` for `phil_scheduling_poll_respondent`; their links,
  which name only the respondent, open nothing more. Running it again finds nothing and reports the same.

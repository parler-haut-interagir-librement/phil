# Suggestions

`TypoSuggester::suggest(EmailAddress|string $address): ?EmailAddress` gives the address a person probably meant when
its domain is a slip away from a well-known one. It never corrects anything: a form asks "Did you mean
`ada@gmail.com`?" and the person decides.

## How

The domain is lowercased; a domain that is not ASCII, or is one of the known domains, gets no suggestion. Otherwise:

1. it is compared with each known domain by the optimal string alignment distance — an insertion, a deletion, a
   substitution or a swap of two neighbours each cost one: `gmial.com` is one from `gmail.com`, `gmailcom` one too;
2. a known domain of fewer than eight characters is skipped: `aol.com` is one slip from `aon.com`, a real domain;
3. one slip is allowed for a known domain of eight or nine characters, two from ten;
4. the closest known domain wins, the first in the list on a tie;
5. failing that, a top-level domain that is a common slip of `com`, `net` or `org` and is not itself delegated —
   `con`, `cmo`, `nte`, `ogr`… — is replaced: `ada@example.con` gives `ada@example.com`.

A known domain whose length differs by more than the limit, or whose Levenshtein distance — which counts a swap as
two — exceeds twice the limit, is set aside before any table is built. Otherwise only the cells within the limit of
the diagonal are computed, and the distance stops as soon as a row can only exceed the limit: the work is bounded by
the 253 octets of a domain and the limit, a few cells per character.

The suggestion is parsed with the suggester's `SyntaxPolicy`: a text whose part before the @ is not valid gets none.

## Tuning

| Argument | Default |
|---|---|
| `$domains` | `TypoSuggester::DOMAINS`: the largest mailbox providers, worldwide and Canadian and French ones, most used first |
| `$slips` | `TypoSuggester::TOP_LEVEL_SLIPS` |
| `$policy` | `new SyntaxPolicy()` |

An application whose members use other providers — a university, a region — passes its own list, most used first,
in ASCII and lowercase; it may start from `TypoSuggester::DOMAINS`.

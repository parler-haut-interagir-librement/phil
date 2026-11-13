# Anonymity

## What an anonymous poll keeps

| Table | Row | What it holds |
|---|---|---|
| `phil_poll_vote` | the ballot | A random identifier, the poll, the choices, and the SHA-256 hash of the receipt. No voter, no time. |
| `phil_poll_participation` | that the voter took part | The poll and the voter. No time, no ballot. |

The participation stops a second ballot of the same voter. Nothing in either table says which ballot is whose: the
identifiers of ballots are random (UUID version 4, not a time-ordered one), neither row carries a time, and the
participation's key is the poll and the voter, with no reference to the ballot.

## The receipt

`cast()` returns, for an anonymous poll, a receipt: 32 random bytes in URL-safe base64, 43 characters. The bundle
keeps only its hash. The application shows the receipt to the voter once — on the confirmation page, in the
confirmation e-mail — and never stores it next to the voter.

With the receipt, the voter changes their ballot while the poll is open (`changeWithReceipt()`) and reads it back
(`ballotOfReceipt()`). Whoever holds a receipt may change its ballot, as long as the policy lets them vote: a receipt
is a secret, like a password reset link. An anonymous ballot is never withdrawn: withdrawing it would have to
remove the participation, which would tell which ballot it was.

## What anonymity does not cover

Anonymity protects the ballots from whoever reads the tables, an export or a backup. It does not protect them from:

- the logs of the database or of the web server, which may record the request that cast a ballot next to its time and
  its user;
- the physical order of rows on some engines — a ballot and its participation are written in the same transaction,
  and an engine that appends rows to a heap stores them near each other;
- a listener of the `VoteCast` event that records the user of the request: the event names the poll only, but it is
  dispatched during that request;
- a poll with very few voters, or a voter alone in choosing an option.

An application that needs a secret ballot in the strongest sense — an election under rules of law — keeps its
database and web logs free of those details, and decides how few voters is too few to show results.

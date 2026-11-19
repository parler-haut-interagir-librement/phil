# Security

A jury evaluation decides who speaks, who is admitted, who wins. The engine enforces the rules that keep it fair; the
application enforces who is who.

## What the engine enforces

- **Phases as permissions.** Every write checks the campaign's phase on the clock: proposals during submission,
  scores and votes during review, decisions during deliberation, conflicts and assignments before deliberation. A
  request that comes late is refused with `Refused`, whatever the screen showed.
- **Conflicts of interest.** A reviewer is in conflict with their own proposals, and with every proposal they or the
  organisers declared — one proposal, or every proposal of one author. In conflict, they are never assigned, never
  review, never vote on a pair that holds the proposal, and see neither its reviews nor its authors.
- **Blindness.** In a blind campaign, `authorsVisibleTo()` gives no author until the proposal's decision is applied,
  and `reviewsVisibleTo()` follows the policy, so that early reviews do not sway later ones.
- **Final decisions.** Decisions stay pending until applied together; an applied decision is not replaced.
- **Bounded input.** Identifiers are 1 to 255 bytes without control characters; a campaign has at most 50 criteria, a
  proposal at most 50 authors, a policy at most 1 000 reviews; weights and scales are bounded; the Bradley–Terry model
  iterates at most 10 000 times.
- **Stored rows.** The DBAL stores bind every value as a parameter; the table prefix, the only name they put in SQL, is
  checked against a strict pattern first. JSON is read back at a depth of 8 at most, and a row they did not write is
  refused, never read as something else.

## What the application enforces

- **Who the caller is.** The engine trusts the reviewer, author, voter and member identifiers it is given. The
  application passes the identifier of the authenticated user, never one read from the request, and checks that a
  reviewer belongs to the jury — for example with a Symfony voter — before calling the board.
- **What the content reveals.** Blindness hides the authors' identifiers; names in a proposal's title, text or files
  are the application's to redact.
- **How often one votes.** A public pairwise vote needs the application's own limits, such as Symfony's rate limiter,
  and its own checks against duplicate accounts.

## Pseudonymous by design

Reviewers, authors, voters and members are referenced by the application's stable opaque identifiers. An identifier
that looks like an e-mail address is refused: an address changes and names a person. Reviews, decisions and votes hold
identifiers and numbers only, and the events carry identifiers only, so that logs and queues hold no personal data.

## Checkable results

Standings are computed from the stored reviews, and pairwise scores from the whole vote log, each time, with no hidden
state: anyone with the reviews or the log computes the same ranking. The pair draw uses a hash of values the log holds,
so the order of the pairs shown can be checked too.

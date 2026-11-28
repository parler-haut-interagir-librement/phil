# Security

A bulk action never bypasses an access rule: everything a single change checks, it checks for each item, at each step.

## What is checked, and when

| Check | Select | Preview | Confirm | Each chunk | Retry |
|---|---|---|---|---|---|
| The target and the action are declared | yes | yes | yes | yes | yes |
| The person may run the action (`Authorizer::mayRun()`) | yes | yes | yes | yes | yes |
| The selection is within the limits; the repository accepts the filter | yes | yes | yes | the filter | |
| The token: known, unexpired, the person's own, unused | | yes | yes, then used | | |
| The change names only the action's fields | | yes | yes | | |
| The item exists and is of the target's class | | sample | | yes | |
| The voter on the item (`Authorizer::mayApply()`) | | sample | | yes | |
| The item's version, when the person saw one | | | | yes, first attempt | |

## The class from the configuration

A request names a target and an action, never a class. `Targets::get()` and `Target::action()` refuse any other name,
and every item a repository gives is checked against the target's class: an identifier of another table can never be
changed through a target.

## Voters for a given person

A run executes in a worker, where nobody is logged in, so the `Authorizer` is asked for the person who confirmed, by
identifier. With Symfony Security, `UserAuthorizationCheckerInterface::isGrantedForUser()` asks the voters for that
user; see [Extension points](extension-points.md). A voter that reads the request or the session sees neither in a
worker.

## Tokens

A selection token is 32 random bytes, valid for one person, once, for `Limits::lifetimeSeconds`. The store keeps its
SHA-256 fingerprint only. Every failure throws `SelectionUnavailable` with the same message, so a token cannot be
probed, and a token held by another person is read before it is taken, so an attempt by someone else never uses it up.

## Requests

Every request that changes something is a POST with Symfony's stateless CSRF token and the browser's origin checked; a
GET shows and never changes. See [Preview and confirmation](confirmation.md#the-requests-a-page-sends).

## Bounds

Every input is bounded: identifiers (191 bytes, 10,000 per selection), names (64 bytes), filters (32 parameters,
16 KiB), changes (64 fields, strings of 64 KiB, lists of 1,000), chunks, samples and lifetimes. A stored selection or
run that toArray() cannot have written is refused when read back.

## What the library keeps

A message carries a run identifier. A run holds the target, the action, the person's identifier, the change, the
selection and an outcome and a translation key per item — never an exception's message, which may quote an item's data.
An exception of the library names the field, the target or the action at fault, never an item's values.

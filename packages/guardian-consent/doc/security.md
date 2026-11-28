# Security and privacy

## What the library keeps

| Data | Kept by | Personal |
|---|---|---|
| A minor: member, jurisdiction, the day of the age of consent, when registered, told and handed over | `MinorStore` | That the member is a minor, and roughly their age |
| A request: member, guardian's identifier and address, features, version of the text, instants, token hash | `RequestStore` | The guardian's address, until the request is answered or erased |
| A proof: member, feature, decision, actor, guardian's identifier, version of the text, request, instant | `ProofStore` | Who decided what for the member |

The date of birth is read and dropped: only the day the age of consent is reached is kept. The guardian's address
stays on the pending request and goes with it — answered, dropped at the handover, or erased by the sweep —; a proof
names the guardian by their identifier. Events carry identifiers only.

## The confirmation tokens

A token is the request's identifier, 16 random bytes, and a verifier, 32 bytes of a cryptographically secure
generator, 256 bits, in URL-safe Base64. The verifier is never stored: the request keeps the HMAC-SHA-256 of its
identifier and verifier under the application's key, so the link is signed by the application and bound to its
request, and a copy of the store opens no link. Hashes are compared with `hash_equals()`, under every key at once.

A token is used once: `decide()` removes the request through `RequestStore::consume()`, which a shared store does
atomically, so that two answers racing get one proof. The request is removed and every proof of the answer written in
one call to `Transactions::run()`: when a write fails, the transaction rolls back, the request is back and its link
answers again, instead of a used token over an answer half recorded. A forged verifier leaves the request untouched. Every refusal
reads the same, so that it tells nothing of which check failed; only an expired request says so, once the verifier has
matched.

The key is 32 bytes at least. To rotate it, pass the new key first and the old one after: links issued under the old
one keep working until it is dropped, after the longest lifetime of a request.

The page the link opens shows the request and decides nothing: the decision is a form, so that a mail scanner that
follows the link changes nothing. The form is protected against cross-site requests the way the application's other
forms are.

## Collection kept to the purpose

The guardian is reached by e-mail with a confirmation. A card transaction or an identity document would prove more
about the guardian, and collect far more than the purpose needs: the library has no port for them.

## Access and erasure

`GuardianConsentRights` implements `Contract\SubjectRights` for the privacy tooling of the application. The subject
is read as a member and as a guardian alike.

| Read as | `export()` | `erase()` |
|---|---|---|
| A member | Their record, the requests about them, the proofs of their consents | All erased |
| A guardian | The requests sent to them, with their address, and the proofs of their decisions | The requests erased; the proofs, which are the member's, no longer name them |

`erase()` returns how many records it erased or changed, and 0 when run again. A subject the library could not have
kept, such as an e-mail address, has nothing.

## Bounds on every input

| Input | Bound |
|---|---|
| A member or guardian identifier | 1 to 255 bytes, without whitespace, control characters or `@` |
| A guardian's address | One `@` between two non-empty parts, without whitespace, at most 254 bytes |
| A feature | 1 to 64 lowercase letters, digits, `.`, `_` or `-` |
| A version of the text | 1 to 64 letters, digits, `.`, `_`, `-` or `+` |
| A date of birth | `YYYY-MM-DD`, not after today, at most 150 years before |
| The features of a request, or of a handover | 1 to 64 for a request, up to 64 for a handover, each once |
| Requests waiting for a member | 5 |
| The lifetime of a request | 1 to 30 days, 7 by default |
| The retention of an unanswered request | 0 to 365 days, 30 by default |
| A sweep | 1 to 1000 records of each kind, 100 by default |
| A token key | 32 bytes at least; up to 8 keys |
| Jurisdictions | 300 |

The reader of confirmation tokens is fuzzed.

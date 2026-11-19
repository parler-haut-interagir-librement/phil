# Security

## Secrets

- The BigBlueButton secret never travels: every call carries a checksum of SHA-2, and the recording token and the
  attendance's bearer token are checked against it in constant time, against the algorithms the adapter accepts, never
  the one a token asks for alone and never `none`.
- The secrets and the private key are marked `#[SensitiveParameter]`: they do not appear in a stack trace.
- The Jitsi webhook signature is compared in constant time, its moment within five minutes, so that a notification
  replayed later is refused.
- Every secret is 16 bytes or more; a JaaS key is an RSA key.

## Answers and notifications

- An XML answer with a document type is refused, so that no entity is declared or expanded, and nothing is loaded from
  the network.
- Every answer, notification and token is bounded in size and depth; see [Errors and budgets](errors.md).
- Every address read from a server, the address of a recording for instance, is read by `phil/url`; one that is not an
  `http(s)` address of a public host is dropped.

## Personal data

- A participant travels by the stable identifier of their account; an e-mail address is refused as an identifier.
- The display name goes in the join link only, to the server; no exception or log of the library holds it.
- Recordings and attendance are personal data of the participants: the application keeps them under its retention
  rules, and gives them in an access request.

## Left to the application

- The proof of the addresses the servers call back, and the check that a notification's room is one of its rooms on
  that server.
- Who is a moderator.
- Where the join links go: a link lets its holder in, for its role, until the room ends or the token expires. Send it
  to its participant only, over https, by a redirection that is not cached.

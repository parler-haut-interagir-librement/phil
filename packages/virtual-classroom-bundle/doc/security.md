# Security

## The webhook

- Every address carries an HMAC-SHA-256 proof of its provider, its room and its channel under `webhook_secret`,
  compared in constant time; a wrong proof is a 404, as an address that does not exist.
- The address of the signed events of a room cannot end it nor reach another room; a notification naming another room
  than its address is refused.
- The provider then checks its own signature: BigBlueButton's tokens, JaaS's `X-Jaas-Signature` within five minutes.
- A body is 1 MiB at most; the readers of phil/virtual-classroom bound the rest.
- Changing `webhook_secret` changes every address: the rooms open at that moment lose their callbacks, and
  `phil:virtual-classroom:sync` makes up for them.

## The join link

- The link of the server is written for the participant of the request and their role, and sent by a redirection 303
  that is not cached and names no referrer.
- A participant is named by the stable identifier of their account; an e-mail address names nobody.
- A GET opens the room: the call is idempotent, and the room is the application's.

## Personal data

- The stays are personal data: exported and erased through phil/privacy-bundle.
- No display name is kept; no log names a participant.

## Left to the application

- Who may join which room: the join link lets in whoever `CurrentParticipant` names. Put the route behind the access
  control the rooms need, or decide in a voter of `moderator_role` and in the participant service.
- Copying a recording before its link expires, and its retention.
- The secrets, in the environment or a vault, never in the repository.

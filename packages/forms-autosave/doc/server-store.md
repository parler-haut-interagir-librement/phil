# The server store

With `server.enabled`, a signed-in user's drafts are also kept on the server, so that they follow the user to another
device. A visitor's drafts stay in their browser.

## Users

`DraftOwner` names the user: by default `TokenDraftOwner`, the user identifier of Symfony Security. The table keeps
only a keyed hash of it, 64 hexadecimal digits, and every read or write is made under the hash of the current user, so
a user never reaches another's draft, even with its key.

## Revisions and conflicts

Each save names the revision it replaces, null for a first save. The server keeps the draft by compare-and-set: an
insertion fails if the draft exists, a replacement if another revision was saved meanwhile. Either way the endpoint
answers `409` with the revision kept, and the controller reads the server's draft and shows the notice of a newer
draft: the user uses it, or keeps the values in the form, which then replace it.

## Encryption

Each draft is sealed by `DraftSealer` in a context naming its owner's hash and its key, so that a sealed draft copied
to another row does not open. With `server.encrypt` on, `KeyManagementSealer` seals by the envelope encryption of
Symfony's KeyManagement component: a fresh data key per draft wrapped by the master key `server.key` names, the
content encrypted locally with AES-256-GCM, the context authenticated with it, the envelope stored in base64. The
envelope names its master key, so drafts sealed before `server.key` changed still open while the KMS holds the old
key. A draft that no longer opens — altered, moved, under a master key the KMS no longer holds, or while the KMS
cannot be reached — is deleted when read. With `server.encrypt: false`, `PlainSealer` stores what
users typed in clear.

## Expiry

A draft is kept `ttl` seconds after its last save. An expired draft is read as none, and a save over it starts a new
one; `phil:forms-autosave:purge` deletes the expired drafts:

```sh
bin/console phil:forms-autosave:purge
```

## The table

`phil_form_draft`: `owner` and `draft_key`, the primary key; `revision`; `payload`, the sealed content; `saved_at` and
`expires_at`, in UTC, the latter indexed for the purge. It is mapped for Doctrine when the server store is on; the
store writes it by plain queries on its connection, which leave the entity manager open when a race is lost.

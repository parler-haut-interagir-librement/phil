# Security notes

## What the bundle defends against

- **Email addresses in identifiers.** Every identifier is 1 to 128 ASCII letters, digits, `_`, `.` or `-`; one with
  `@` is refused, so that no address reaches a table, a log or an event by way of a member identifier.
- **Privilege escalation through grants.** A member grants, changes or removes only roles whose permissions they hold
  themselves, on members whose roles are within their own; an admin cannot name, demote or remove an owner. An
  invitation is refused when its creator may no longer invite and grant its roles.
- **Orphaned organizations.** The last owner can never leave, be removed or lose the owner role. Each change locks the
  organization's row — `SELECT … FOR UPDATE` on PostgreSQL, MySQL and MariaDB; SQLite writes one transaction at a time
  —, so that two concurrent changes cannot both remove an owner.
- **Stolen tables.** An invitation's token is kept only as its HMAC-SHA-256 under the application's key, under keys
  that rotate; the token appears in no event, log or table. Hashes are looked up by a unique index, and a keyed hash
  says nothing of a token by its timing.
- **Double use.** Each use of an invitation is one conditional `UPDATE` whose row count is checked: an invitation is
  never used more times than it allows, nor after it expired or was revoked.
- **Enumeration.** A refused invitation throws the same message whatever the cause; the cause is a property for the
  application, not for the visitor.
- **Case confusion.** Every identifier and key column compares byte for byte — `ascii_bin` on MySQL and MariaDB, the
  `C` collation on PostgreSQL —, so that `Acme` is never `acme`.
- **Phantom events.** Events are dispatched once the transaction is committed, never for a change rolled back.
- **Personal data in events.** Events carry identifiers and role names only; a verified address given to join by a
  domain is read for its domain and never kept, logged or dispatched.
- **Workers.** No service keeps state between requests; the context of `OnBehalf` and the store in memory are reset
  between them.

## What the application does

- Authenticate members and give the bundle their stable subject identifier, never an email address.
- Verify an email domain before recording it, and a member's address before joining by the domain.
- Send invitation links over a channel it trusts, and keep `ORGANIZATIONS_INVITATION_KEY` in the secrets vault.
- Check the permissions of its own screens with the voter, and escape names on output like any text a member typed.
- Name a new owner when `OrganizationLeftWithoutOwner` is dispatched, after checking the request out of band.
- Rate-limit the endpoint that accepts invitations, so that guessing tokens costs more than 256 bits already do.

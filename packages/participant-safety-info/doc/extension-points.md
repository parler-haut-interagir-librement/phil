# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

## Ports the application implements

| Port | What it answers | Where it is read |
|---|---|---|
| `Contract\SafetyInfoStore` | The sealed records, one per event and participant; the deletion of those kept until before a date, by lots | `SafetyInfoVault` |
| `Contract\EventRoles` | The `Role` of a person for an event: responsible, team, or none | `read()`, `breakGlass()` |
| `Contract\AccessWindows` | The `AccessWindow` of an event, or `null` | `submit()`, `read()`, `breakGlass()` |
| `Contract\AccessJournal` | The readings by someone else, written before the opening; the latest of a participant | `read()`, `breakGlass()`, `readingsOf()` |
| `Contract\SafetyInfoSealer` | Seals and opens a text under a context | `SafetyInfoVault`; `KeyManagement\KeyManagementSealer` |

`Memory\InMemorySafetyInfoStore` and `Memory\InMemoryAccessJournal` implement the store and the journal in memory,
for tests and demonstrations; they keep their records for the life of the object.

An implementation of the store keeps a unique key on the event and the participant, deletes by lots of at most
`$limit` rows, and never logs the sealed text. An implementation of the journal fails loudly rather than drop a
record: the vault stops the reading when it throws.

## What an application may replace

| To | Do |
|---|---|
| Seal with something other than Symfony's KeyManagement | Implement `Contract\SafetyInfoSealer` with an authenticated encryption that binds the context; never a plaintext pass-through |
| Tell the responsible people of a broken glass | Listen to `Event\SafetyInfoRead` and act when its reason is `AccessReason::BreakGlass` |
| Erase the information of a member who leaves | Call `erase()` for each of their events from the application's erasure flow |
| Purge more or less at a time | Pass `$limit` to `purge()`, 1 to 10,000 |

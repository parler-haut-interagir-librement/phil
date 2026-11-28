# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

## Ports the application implements

| Port | What it answers | Where it is read |
|---|---|---|
| `Contract\CodeVersionStore` | The versions of each scope: the current one, one by number, the latest major number; adds a version | `CodeOfConduct`, `Reports::file()` |
| `Contract\AcceptanceStore` | The acceptance of the highest version by a member; adds an acceptance | `CodeOfConduct` |
| `Contract\ReportStore` | The reports by identifier and by scope, oldest first; their notes | `Reports` |
| `Contract\ResponsiblePeople` | The responsible people of a scope, 100 at most | `Reports` |
| `Contract\TextSealer` | Seals and opens a text under a context | `Reports`; `KeyManagement\KeyManagementTextSealer` |

`Memory\InMemoryCodeVersionStore`, `Memory\InMemoryAcceptanceStore` and `Memory\InMemoryReportStore` implement the
stores in memory, for tests and demonstrations; they keep their records for the life of the object.

An implementation of the version store keeps a unique key on the scope and the number, so that two publications at
once never both succeed. An implementation of the report store reads a page with one query and never logs a sealed
text.

## What an application may replace

| To | Do |
|---|---|
| Seal with something other than Symfony's KeyManagement | Implement `Contract\TextSealer` with an authenticated encryption that binds the context; never a plaintext pass-through |
| Apply an outcome | Listen to `Event\ReportStatusChanged` and act through the application's moderation |
| Keep an audit trail of who read which report | Listen to `Event\ReportViewed` |
| Tell the members of a major change | Listen to `Event\CodeVersionPublished` and read the version |
| Hold back taking part | Ask `CodeOfConduct::status()` before a write, and show the code when `mayTakePart()` is false |

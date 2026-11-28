# Reports

Every path to the reports goes through `Reports`. Each method takes the identifier of a person the application has
already authenticated: the service decides what that person may do, never who they are. An identifier holds 1 to 255
bytes without a control character; use a stable identifier, never an email address.

## Filing

`file()` takes the scope, the member who files the report or `null` for an anonymous report, the members it names —
20 at most — and what happened, 1 to 20,000 characters. It records the version of the code in force, seals the text
and saves the report as `ReportStatus::Received`. The `ReportFiled` event names the report and its scope; a listener
tells the responsible people who may handle it.

A report no responsible person could receive — the scope has none, or the report names them all — is refused with
`ActionRefused`, and the application points the member to the contact outside the site.

## The receipt

`receipt()` shows the member who filed a report that it was received, where it stands and since when: never the
notes, the outcome or who handles it. An anonymous report has no receipt.

## Who handles a report

A person handles a report when they are among the responsible people of its scope, as `Contract\ResponsiblePeople`
lists them now — 100 at most — and the report does not name them, and they did not step aside. Anyone else gets
`ReportUnavailable`, whose message is the same whether the report does not exist or may not be seen.

| Path | What it does | Event |
|---|---|---|
| `queue()` | The reports of the scope the person may handle, oldest first, 1 to 100 at a time, by status | None |
| `read()` | The report, its text and its notes, opened | `ReportViewed`, before the texts are opened |
| `addNote()` | A note of 1 to 10,000 characters, sealed; 500 notes per report at most | `ReportNoteAdded` |
| `review()` | Takes a report received, resolved or dismissed into review | `ReportStatusChanged` |
| `resolve()` | Closes a report in review with an `Outcome` | `ReportStatusChanged` with the outcome |
| `dismiss()` | Closes a report received or in review without an outcome | `ReportStatusChanged` |
| `recuse()` | The person steps aside: from then on, they no longer see the report | `ReportRecused` |

A page of `queue()` leaves out the reports the person may not handle, so it may hold fewer than asked; the next page
starts after `ReportPage::$next`, `null` on the last page.

## Statuses and outcomes

| From | May become |
|---|---|
| `Received` | `InReview`, `Dismissed` |
| `InReview` | `Resolved`, `Dismissed` |
| `Resolved`, `Dismissed` | `InReview` |

An outcome goes with `Resolved` only: `NoAction`, `Correction`, `Warning`, `TemporaryExclusion` or
`PermanentExclusion`. The package records it; the application applies it through its moderation, from the
`ReportStatusChanged` event.

## The sealing of the texts

The text of a report and each note are sealed by `KeyManagement\KeyManagementTextSealer`, the envelope encryption of
Symfony's KeyManagement component: a fresh data key per text, wrapped by the master key, the text encrypted locally
with AES-256-GCM. The context authenticated with a text names the report, and the note for a note: a text moved to
another report does not open, and `read()` refuses it with `UnreadableText`. The store keeps the scope, the people
named, the reporter, the statuses and the dates in plaintext, so that it can find and list the reports.

`ReportView` and `NoteView` never dump their texts: `var_dump()`, `print_r()` and the debuggers show `[sensitive]`.

## Events

Every event carries identifiers only — the report, the scope, the note, the person, the status, the outcome — never a
text. `ReportViewed` is the trace of who opened which report; an application that keeps an audit trail listens to it.

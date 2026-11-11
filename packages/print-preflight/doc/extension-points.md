# Extension points

The library is plain PHP and declares no service. What an application adds is checks; what it describes is the
printer and the document.

## `Check`

`Phil\PrintPreflight\Check\Check` — one family of rules run over a document. See
[Checks and findings](checks.md#adding-a-check).

| Method | Contract |
|---|---|
| `check(Document $document, PrintProfile $profile): list<Finding>` | The findings at their own severity, each with a code of its own — `app.spine` rather than a built-in `Rule` value; never throws for a document that breaks a rule |

The profile's severities and ignored codes apply to a check of the application's as to the built-in ones.

## Preflights

| Point | How |
|---|---|
| Adding a check | `Preflight::withCheck()` |
| Replacing the built-in checks | `new Preflight($profile, [...])`, with `Preflight::defaultChecks()` to keep some |
| What a printer asks | `PrintProfile`: trim sizes, bleed, safety margin, page counts, ink coverage, line width, overprint and transparency, and the severity of each rule or its code ignored — see [Printer profiles](profiles.md) |
| Where the document comes from | `Document`, built by the application from a `phil/paged-media` document, from a browser's render, or from any layout it measures — see [The document model](model.md) |

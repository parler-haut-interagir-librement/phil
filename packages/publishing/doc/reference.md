# Reference

Every public type of the package, with its role.

| Type | Role |
|---|---|
| `Phil\Publishing\PagePublished` | A page went online, or online again with new content: canonical address, language, indexable flag, owner identifier |
| `Phil\Publishing\PageRetired` | A page went offline, taken offline or deleted: the same four fields, `indexable` as it was while online |
| `Phil\Publishing\Contract\PageEvent` | The four read-only fields both events carry, for a listener that treats them alike |
| `Phil\Publishing\Exception\InvalidPageEvent` | A language that is not a BCP 47 tag, or a malformed owner identifier |
| `Phil\Publishing\PageEventFields` | Internal: the checks of the language and of the owner identifier |

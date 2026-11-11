# Usages

| Method | What it gives |
|---|---|
| `usagesOf($target)` | Where a resource is used, by source then field |
| `assertUnused($target, $leaving)` | Nothing, or `StillReferenced`; sources in `$leaving` go away with it |
| `broken()` | The usages whose target is gone, for the kinds a target knows: each target asked once whether it exists, then the usages of every target gone read at once, by chunks of 500 targets per kind |
| `refresh($source)`, `forget($source)` | What a source links to now, only the changes written; nothing any more |
| `forgetSource($reference)` | Nothing any more, for a source known by its reference |
| `referenceOf($resource)`, `sourceOf($source)` | The reference a target or a collector gives an object |

`StillReferenced` names the first three sources and how many more: *image:hero is still used by page:about
(blocks.1.image), page:home (hero) and 2 more.* Its `usages` holds them all.

```bash
bin/console phil:references:check
# page:about (blocks.2.image) → image:gone, gone
```

The command fails while there is a broken link: run it in a scheduled task, and fix the sources it names.

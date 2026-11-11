# Exceptions

Every exception implements `Phil\TextDiff\Exception\TextDiffException`, and each extends `InvalidArgumentException`.

| Exception | Thrown when |
|---|---|
| `InvalidText` | a text given to `compare()`, `words()`, `lines()`, `paragraphs()` or `html()` — or a text field of a document — is not valid UTF-8 |
| `InvalidConfiguration` | `TextDiff` is built with a negative bound of edits |

The messages name what is wrong:

```
The text cannot be compared: it is not valid UTF-8.
The number of edits searched for cannot be negative; got -1.
```

`documentToHtml()` lets through `Phil\Ujo\Exception\UnknownBlockType` from `phil/ujo` when the renderer has nothing
for a block's type. Comparing documents never throws on their structure: any two `Document` values can be compared.

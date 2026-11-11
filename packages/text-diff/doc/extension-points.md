# Extension points

The library is plain PHP: it declares no service and no port, and its classes are final. What an application
chooses goes through arguments.

| Point | How |
|---|---|
| The bound of the work | The `$maxEdits` of `TextDiff`: past it, what is left is given as deleted then inserted whole rather than computed further, so a hostile pair of texts costs a bounded time |
| How a text is cut | `Tokenizer` given to `compare()`, or `words()`, `lines()`, `paragraphs()` and `html()` |
| How differences are shown | `toHtml()` gives `<ins>` and `<del>`; another presentation reads the `Operation`s of a `Diff`, or its `Change`s with their offsets |
| How the blocks of a document are rendered | The `HtmlRenderer` of `phil/ujo` given to `documentToHtml()`: the application's block renderers render its own blocks in a difference |
| Catching the library's errors | Every exception implements `Phil\TextDiff\Exception\TextDiffException` |

The algorithm is Myers' and is not replaced; the tokens it compares are what an application chooses.

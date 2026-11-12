# Exceptions

The bundle's exceptions implement `Phil\TranslationBundle\Domain\Exception\TranslationBundleException`.

| Exception | Extends | Thrown when |
|---|---|---|
| `Domain\Exception\InvalidRecord` | `InvalidArgumentException` | A document reference is not 1 to 255 bytes; a term is longer than 255 bytes; a row is given a segment of another key or another term; a person is referred to by none or more than 255 bytes; flags are longer than 1024 bytes; a comment is empty, longer than 4000 characters or not UTF-8 |
| `Application\Exception\NotFound` | `RuntimeException` | A segment the document does not have; a document not prepared in the language, exported, imported or machine translated; a term the glossary does not have; a comment the document does not have |
| `Application\Exception\MachineTranslationFailed` | `RuntimeException` | Machine translation while it is off; a translator that throws, or does not answer one target per source |

`Translations` also lets through the library's `InvalidSegment` — a change a segment cannot take —, `InvalidXliff` and
`InvalidLanguage` — a file that cannot be read, or of other languages —, and `InvalidConfiguration` — a check that is
not a flag's name; `Glossaries` lets through `InvalidTbx`. The messages name what is wrong:

```text
Document "article:42" has no segment "b9/text" in "fr".
Document "article:42" has no segments in "fr": prepare it first.
The glossary from "en" to "fr" has no term "dragon".
```

The screen turns them into HTTP answers, as [the translation screen](screen.md) lists.

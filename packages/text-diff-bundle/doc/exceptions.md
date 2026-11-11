# Exceptions

The bundle has no exception of its own.

| When | What is thrown |
|---|---|
| The container is built with a negative `max_edits` or an unknown `tokenizer` | `Symfony\Component\Config\Definition\Exception\InvalidConfigurationException` |
| `text_diff` is given a tokenizer name other than `words`, `lines`, `paragraphs` or `html` | `InvalidArgumentException` |
| A text compared is not valid UTF-8 | `Phil\TextDiff\Exception\InvalidText` |
| `text_diff_document` is given JSON that is too large, not JSON, or not a document | `Phil\Ujo\Exception\InvalidJson` or `Phil\Ujo\Exception\InvalidDocument` |
| A block of a document has a type the renderer does not know | `Phil\Ujo\Exception\UnknownBlockType` |

In a template, Twig wraps them in a `Twig\Error\RuntimeError` that names the template and the line.

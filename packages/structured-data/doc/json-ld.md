# JSON-LD

## The document

`JsonLd::encode(Graph $graph, bool $pretty = false)` writes the graph as one JSON-LD document:

```json
{"@context":"https://schema.org","@graph":[{"@type":"WebSite","@id":"https://example.com/#website","name":"Le Gnome","url":"https://example.com/"}]}
```

A graph with a language writes it in its context, after the schema.org one:

```json
{"@context":["https://schema.org",{"@language":"fr-CA"}],"@graph":[…]}
```

A single node is still written under `@graph`: the document has one shape, whatever it holds.

## The script element

`JsonLd::script(Graph $graph, ?string $nonce = null)` gives the one element a page prints, or an empty text for an
empty graph:

```html
<script type="application/ld+json" nonce="…">{"@context":"https://schema.org","@graph":[…]}</script>
```

The data cannot end the element, open a comment in it or break a parser:

| Written | As |
|---|---|
| `<`, `>` | `\u003C`, `\u003E`: `</script>` and `<!--` never appear |
| `&` | `\u0026` |
| U+2028, U+2029 | `\u2028`, `\u2029` |
| Text that is not valid UTF-8 | U+FFFD, rather than a failed page |
| `/`, letters outside ASCII | Themselves, so the document stays readable |

The nonce is the one of the page's content security policy, given by the application; it is base64 text of 1 to 256
characters, and anything else is refused rather than escaped. A browser runs no JSON-LD, but a strict policy or an
auditing tool may still ask every script element for its nonce.

`JsonLd::FLAGS` holds the flags of `json_encode()` the library writes with.

## Reading a page back

`JsonLdReader` reads what a page holds, to check it or to compare it with what the application meant to print:

| Member | Gives |
|---|---|
| `JsonLdReader::fromHtml(string $html)` | An `Extraction`: the graph of every JSON-LD element merged by `@id`, how many elements there were, and why each unreadable one was skipped |
| `JsonLdReader::fromJson(string $json)` | The graph of one document: a node, a list of nodes, or a map with a `@graph`; the `@language` of its context becomes the graph's |

An element is read when its `type` is `application/ld+json`, whatever its case and spaces. An element that is not JSON,
not a graph of nodes, or holds what a node refuses is skipped and named in `Extraction::$errors`; the others are still
read.

The reader reads pages written by anyone, so its work is bounded:

| Bound | Value |
|---|---|
| A page | 5,000,000 bytes (`MAX_HTML_BYTES`); a larger one is refused |
| JSON-LD elements read | 64 (`MAX_BLOCKS`); the others are skipped, with an error |
| JSON depth | 64 levels (`MAX_JSON_DEPTH`) |
| Node depth | 32 levels (`Node::MAX_DEPTH`) |
| Nodes in the graph | 10,000 (`Graph::MAX_NODES`) |

The HTML is parsed by the HTML5 parser of PHP's DOM extension, errors silenced: a page is read as a browser reads it.

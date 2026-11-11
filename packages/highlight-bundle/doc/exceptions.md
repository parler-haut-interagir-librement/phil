# Exceptions

The bundle has no exception of its own.

| When | What is thrown |
|---|---|
| The container is built with an alias, or a name, that is not written in lower case with letters, digits and `_+#.-`, up to 32 characters | `Symfony\Component\Config\Definition\Exception\InvalidConfigurationException` |
| The highlighter is first built with an alias to a name no language has | `Phil\Highlight\Exception\UnknownLanguage`, listing the languages |
| The highlighter is first built with a tagged language whose name or alias cannot be asked for | `Phil\Highlight\Exception\InvalidLanguage` |

The highlighter is built on first use, so the last two are thrown where it is first asked for: a service, the filter,
the rendering of a UJO document. Colouring code never throws: a language the highlighter does not know gives the code
escaped.

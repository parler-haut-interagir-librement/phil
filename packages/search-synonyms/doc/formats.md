# Formats

## The Solr synonyms format

`SolrSynonymsReader::read($text, 'fr')` reads the text format Solr and many engines load; `SolrSynonymsWriter` writes
it back.

```text
# Synonyms of the games
jdr, jeu de rôle
donjon => donjon, dungeon
tic\, tac\, toe, morpion
```

| Line | Read as |
|---|---|
| Phrases separated by commas | An equivalent rule |
| Phrases, `=>`, phrases | A one-way rule |
| Starting with `#` | A comment |
| Blank | Nothing |

A backslash makes the next character plain: `\,` a comma, `\=>` an arrow, `\\` a backslash, `\#` a `#` at the start
of a line; any other escaped character is itself. White space around a phrase does not count. A UTF-8 byte order
mark, and line breaks of any system, are read.

A file is 1 MiB at most and a line 4096 bytes; a file that is not valid UTF-8 is refused, and a line that is no rule —
an empty phrase, two arrows, a backslash at its end, a rule outside the [limits](rules.md#phrases) — is refused with
`InvalidSynonyms`, whose `fileLine` holds its number from 1.

The writer starts with a comment naming the language, writes a rule per line, and escapes what the reader would read
otherwise: what it writes is read back as the same rules.

## JSON

`JsonSynonyms` reads and writes a document fit for an export, an import, or an administration that edits the rules in
a form:

```json
{
    "language": "fr",
    "rules": [
        {"synonyms": ["jdr", "jeu de rôle"]},
        {"from": ["donjon"], "to": ["donjon", "dungeon"]}
    ]
}
```

A rule is equivalent phrases under `synonyms`, or a one-way rule with `from` and `to`. A document is 1 MiB at most and
four levels deep; one that is not shaped so is refused with `InvalidSynonyms`, which names the position of its rule
from 1. The writer pretty-prints, slashes and accents as they are.

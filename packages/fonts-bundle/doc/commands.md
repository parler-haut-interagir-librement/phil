# Commands

| Command | What it does | Exit code |
|---|---|---|
| `phil:fonts:list` | One line per face of each file: file, format, family, style, weight, embedding permission, SPDX identifier of the licence (`?` when none is recognised); a file that cannot be read shows why | 1 when a file cannot be read for another reason than a missing `ext-brotli`, else 0 |
| `phil:fonts:licences [--write]` | Lists the files whose licence must be checked; `--write` records them | 1 while a file is to check and `--write` is not given, else 0 |
| `phil:fonts:css [--output=FILE]` | Prints the `@font-face` rules, or writes them to a file | 0 |

`phil:fonts:list` writes "Not read: ext-brotli is not loaded" for a WOFF2 file read without the extension, and a note
under the table that the WOFF, TrueType or OpenType files are read instead. An empty or missing directory is said in
one sentence.

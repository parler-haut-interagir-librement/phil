# How it indexes

Each index of `phil/search-bundle` is a Loupe index of its own, in a directory named for it under the configured
directory, or in memory without one. That directory holds `phil_search.json`, a lock file, and the directory of
Loupe's files that `phil_search.json` names: `loupe`, then `loupe-` and twelve hexadecimal digits after a rebuild or
a new field.

- **Only the text fields are searched**, and a document is found when it holds every word looked for, as with the
  bundle's `dbal` adapter; Loupe ranks the documents found, its typo tolerance included.
- **The filters are kept as lists of text**: Loupe gives each field one type, and a filter may be a value or a list.
  A list matches when one of its values does; a value is compared as text. A query's filter that is a list finds a
  document having any of its values. A filter on a field no document has finds nothing.
- **Facets are Loupe's facet distribution**, on the filtered fields: a facet whose own filter is set is counted by
  one more search without that filter and without hits, so that its other values keep their counts. A facet on a
  field no document has as a filter gives no value.
- **Synonyms are not applied**: Loupe reads neither the alternatives `phil/search-bundle` gives the terms of a query
  nor a list of its own; the words are searched as they are.
- **The fields are learned as documents come.** Loupe declares an index's searched and filtered fields before it
  indexes: the adapter keeps them in `phil_search.json`, beside the index, and widens them when a document brings a
  new one. The index is then filled again with the documents it holds — a moment longer, once, about a millisecond
  per document: on disk, they are copied a page at a time into a new directory of Loupe's files, under the lock,
  which `phil_search.json` then names, and the old directory is deleted, as a rebuild does. Loupe is never left to
  migrate its own files: Loupe 1.1.6 fails that migration once an index holds a hundred documents or more.
- **The searched fields keep the order they came in**, which Loupe ranks by: a provider that gives `title` before
  `body` has a match in the title outrank one in the body. A field learned later comes after the others.
- **Every process sees the same fields.** The file is checked before each use, so a long-running worker sees a
  field a web request added, or a rebuild another process made. It is kept in memory and read again only when its
  inode, size or modification time changed — every write replaces it by a rename —, or when it changed within the
  current second, which a modification time cannot tell apart. It is widened under a lock, read again inside it, so
  that no process writes back fewer fields than another learned.
- **A write never lands in a directory being copied.** Adding, removing and clearing documents hold the same lock,
  shared: writes run side by side, and wait while a process widens the fields or switches to a rebuilt directory.
- **A rebuild keeps the index searchable.** `rebuild()` fills a new directory of Loupe's files beside the live one,
  then names it in `phil_search.json` and deletes the old one; a rebuild that fails deletes its own directory and
  leaves the index as it was. In memory, the index is replaced once every document is read.
- An index name that is not one — 1 to 64 lower-case letters, digits or `_` — is refused before any path is built.
- A page is `limit` and `offset`, at most 1000 documents, and the total is Loupe's count of the documents found.

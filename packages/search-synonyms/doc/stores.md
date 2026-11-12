# Stores

`SynonymStore` keeps the list of each language:

| Method | Contract |
|---|---|
| `get(string $language): SynonymList` | The list of a language, read as its primary subtag; an empty list for a language without one |
| `save(SynonymList $list): void` | Replaces the list of its language |
| `languages(): list<string>` | The languages with a list, in alphabetical order |

## Files

`FileSynonymStore` keeps a file per language in a directory, in the [Solr format](formats.md): `fr.txt`, `en.txt` —
files a project keeps under version control, or that an administration writes. Each file is read when asked, so a
long-running worker sees the last one saved; a save writes a file beside it, then moves it in place, so a reader never
sees half a file. The directory is created when the first list is saved. A file holding a line that is no rule is
refused when it is read.

## Memory

`InMemorySynonymStore` keeps the lists it is given, for tests and for lists the application builds itself.

## A store of the application's

A table the administration edits is a class implementing `SynonymStore`, given to the container as any service.

## With phil/search-bundle

`phil/search-bundle` reads a store through its synonyms port:

```yaml
phil_search:
    synonyms:
        directory: '%kernel.project_dir%/config/synonyms'   # a FileSynonymStore
        # or
        store: App\Search\TableSynonymStore                  # a store of the application's
```

An equivalent rule stays equivalent and a one-way rule stays one-way; the bundle reads the rules of a language at the
first search of that language in a request.

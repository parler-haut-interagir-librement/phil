# Testing

## The bundle's tests

From the monorepo root:

```sh
vendor/bin/phpunit packages/epub-bundle/tests
```

`tests/Unit` writes books and reads their archives back, keeps them in temporary directories and in a media library held
in memory, embeds font files written byte by byte, and runs the handler and the command against storages that fail.
`tests/Functional` boots an application with FrameworkBundle, Messenger handling at once and `phil/fonts-bundle`,
dispatches `WriteEpub` to a source registered by attribute, and checks the book kept and the event. The bundle is held to
100 % covered mutation score by Infection.

## Testing an application that uses it

Point `storage.directory` at a temporary directory in the test environment and route `WriteEpub` to the `sync://`
transport, then read the archive with `ZipArchive`. A book source is a plain class: build its book in a unit test and
compare `Epubs::write()` with a reference file, since the same book gives the same bytes.

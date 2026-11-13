# Licences

A font file is software under a licence. The register records, for each file, its SHA-256 and what each of its faces
says about its licence — description, URL, SPDX identifier, embedding permissions — the day someone checked it. The
catalogue is then compared with it: a file added, removed or changed since is a licence to check again.

`Licences` holds the register at `licence_register`:

| Method | Returns |
|---|---|
| `kept()` | The register as last written; empty when the file does not exist |
| `current()` | The register of the catalogue now, files that cannot be read keeping their record (see [The catalogue](catalogue.md#licence-records)) |
| `changedFiles()` | The files whose licence must be checked, sorted |
| `write()` | Writes `current()` to the file, creating its directory, and returns it |
| `path()` | The path of the register |

A register that is not valid JSON throws `FontUnreadable`, with the library's `InvalidLicenceRegister` as previous
exception.

## In a build

```sh
bin/console phil:fonts:licences
```

Each file to check is listed with what happened to it — added, removed, changed — and the command exits with 1, so a
CI step stops. Once the licences are read, `--write` records the files as they are; commit the register with them.

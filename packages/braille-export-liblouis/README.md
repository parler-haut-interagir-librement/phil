# phil/braille-export-liblouis

**Contracted and uncontracted braille for phil/braille-export by liblouis, in every language it has tables for.**

phil/braille-export-liblouis is the liblouis translator of `phil/braille-export`. `LiblouisTranslator` implements the
package's `Translator` port by running `lou_translate`, the translation program of liblouis: a whole document goes in
one run, one text per line of its standard input, and comes back as Unicode braille, one line per text.

Reach for it for contracted braille — « braille abrégé », grade 2 —, for a language other than French and English, or
for the full rules of a code. The uncontracted translator of `phil/braille-export` needs no program, and takes over
when liblouis is missing through a `ChainTranslator`.

The package holds no table and no code of liblouis: liblouis is installed on the machine, with its tables, from the
packages of the system or in the image of the application. See [Installation](doc/installation.md).

`LiblouisTables` chooses the table of each language and contraction: Unified English Braille and the Code braille
français uniformisé by default. The program is started with its arguments as a list, never through a shell; its time,
its input and its output are bounded, and a missing program or table throws its own exception. See
[Security](doc/security.md).

## Status

| Part | State |
|---|---|
| `LiblouisTranslator`: batches, pieces of long texts, Unicode braille checked, limits, version | Implemented and tested with a stand-in program |
| `LiblouisTables`: the table of each language and contraction | Implemented and tested |
| The exceptions: missing program, missing table, other failures | Implemented and tested |
| The integration suite against the real `lou_translate` | Written; skipped on a machine without liblouis |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing liblouis from the system or in a Docker image, the service wiring. |
| [Configuration](doc/configuration.md) | The tables, the display table, the table path, the program, the limits. |
| [Security](doc/security.md) | How the program is started, and what bounds it. |
| [Exceptions](doc/exceptions.md) | What the translator throws, and when. |
| [Extension points](doc/extension-points.md) | The arguments an application chooses, and what it replaces. |
| [Testing](doc/testing.md) | The stand-in program of the unit tests, and the suite of the real one. |
| [Reference](doc/reference.md) | Every public type, with its role. |

## Quick start

```sh
composer require phil/braille-export-liblouis
```

```php
use Phil\BrailleExport\BrailleExporter;
use Phil\BrailleExport\Contraction;
use Phil\BrailleExport\Output\BrfWriter;
use Phil\BrailleExport\Translation\ChainTranslator;
use Phil\BrailleExport\Translation\UncontractedTranslator;
use Phil\BrailleExportLiblouis\LiblouisTranslator;

$translator = new ChainTranslator(new LiblouisTranslator(), new UncontractedTranslator());
$book = new BrailleExporter($translator)->layout($document, Contraction::Contracted);

file_put_contents('book.brf', new BrfWriter()->write($book));
```

## Requirements

PHP 8.5 or later with `mbstring`; `phil/braille-export` 0.1; `symfony/process` 8.2; liblouis 3 with its
`lou_translate` program and its tables on the machine that translates.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See
[LICENSE](LICENSE).

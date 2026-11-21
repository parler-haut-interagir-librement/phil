# Installation

```sh
composer require phil/braille-export-liblouis
```

The package needs PHP 8.5 or later with `mbstring`, `phil/braille-export` 0.1, `symfony/process` 8.2 and `psr/log` 3.

## Installing liblouis

liblouis is a library under the LGPL 2.1 or later, its translation tables, and command line programs under the GPL 3
or later, `lou_translate` among them. The package runs `lou_translate` as a separate program, through Symfony Process:
it ships no part of liblouis, links to none and loads none into PHP, so its own MIT licence stays whole and the terms of
liblouis bind only the machine that installs it. Install it on every machine that translates.

| System | Command |
|---|---|
| Debian, Ubuntu | `apt-get install liblouis-bin liblouis-data` |
| macOS, with Homebrew | `brew install liblouis` |

In a Docker image of the application, the same line goes in its `Dockerfile`:

```dockerfile
FROM php:8.5-cli
RUN apt-get update \
    && apt-get install --no-install-recommends -y liblouis-bin liblouis-data \
    && rm -rf /var/lib/apt/lists/*
```

`lou_translate --version` then names the version, and `LiblouisTranslator::version()` reads it. The translator needs
liblouis 3.39.0 or later, which fixes memory-safety defects, and refuses an older one by default: the package of a
system may be older, so check the version it installs, and install a later liblouis from its releases when it is. An
application that must run an older one for a time says so with `$allowOutdatedLiblouis`; see
[Configuration](configuration.md#the-oldest-liblouis).

The tables are installed in the table directory of liblouis, such as `/usr/share/liblouis/tables`. Tables kept
elsewhere are given by `$tablePath`; see [Configuration](configuration.md).

## Wiring the service

The package has no bundle. In a Symfony application, `config/services.yaml` makes it the translator, with the
uncontracted translator behind it:

```yaml
services:
    Phil\BrailleExportLiblouis\LiblouisTranslator: ~
    Phil\BrailleExport\Translation\UncontractedTranslator: ~
    Phil\BrailleExport\Contract\Translator:
        class: Phil\BrailleExport\Translation\ChainTranslator
        arguments:
            - '@Phil\BrailleExportLiblouis\LiblouisTranslator'
            - '@Phil\BrailleExport\Translation\UncontractedTranslator'
```

`LiblouisTranslator::isAvailable()` says whether the program is found, for a health check or a deployment check;
`version()` compared with `LiblouisTranslator::MINIMUM_VERSION` says whether it is recent enough.
`supports()` reads the tables only: on a machine without liblouis, the chain gives a contracted batch to liblouis all
the same, and `ExecutableNotFound` says so. An application that would rather fall back to uncontracted braille puts
`LiblouisTranslator` in its chain only when `isAvailable()` says yes.

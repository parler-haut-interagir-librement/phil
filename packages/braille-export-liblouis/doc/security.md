# Security

The texts may come from anybody. The translator keeps them away from the shell and bounds what they cost.

- **No shell.** The program is started by `symfony/process` with its arguments as a list: `lou_translate`,
  `--forward` and the display and translation tables. The texts never reach the command line; they go on its standard
  input, one per line, their control characters made spaces, so that no text can add a line.
- **No path from outside.** The program is a name looked for on the `PATH`, or a path the application gives. A table is
  a file name, checked against letters, digits, `.`, `_` and `-`: no `/`, no `..`, nothing that starts with `-`. The
  directories of `LOUIS_TABLEPATH` are the application's configuration.
- **Bounded time.** `$timeout`, 30 seconds by default, stops a batch.
- **Bounded size.** `$maxInputBytes`, 4 MiB by default, refuses a batch before the program starts; `$maxOutputBytes`,
  32 MiB by default, stops the program as soon as it writes more, error output included; the error output kept in an
  exception is at most 64 KiB.
- **Checked answers.** The program must give back one line per line sent, each of braille cells: anything else stops
  the batch, so that nothing but braille reaches a file.
- **A liblouis without known memory-safety defects.** liblouis 3.39.0 fixes memory-safety defects, and the texts the
  program reads come from documents nobody checked. Before its first translation, the translator asks the program its
  version, `lou_translate --version`, with the same timeout as a batch and an answer of at most 4 KiB, and refuses a
  version older than 3.39.0 with an `OutdatedLiblouis`, and an answer that names no version with a `LiblouisFailed`.
  Nothing is sent to a liblouis it refuses. `$allowOutdatedLiblouis` lets such a liblouis translate, with a warning in
  the PSR-3 log given as `$logger`; see [Configuration](configuration.md#the-oldest-liblouis).
- **Nothing else is kept.** Between two batches, the translator keeps only the version the program gave, the same for
  every request.

The messages of the exceptions name the failure, never the text; the error output of the program may quote it, and
belongs in a log the application trusts.

The tables are data of liblouis under its licence, installed with it; the package embeds none.

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
- **Nothing is kept.** The translator holds no state between two batches.

The messages of the exceptions name the failure, never the text; the error output of the program may quote it, and
belongs in a log the application trusts.

The tables are data of liblouis under its licence, installed with it; the package embeds none.

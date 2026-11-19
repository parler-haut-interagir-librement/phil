# Exceptions

Every exception of the translator extends `Exception\LiblouisException`, which implements `TranslationFailed` of
`phil/braille-export`. It carries the code the program ended with, `$exitCode`, null when it did not end by itself or
did not start, and the first 64 KiB of its error output, `$errorOutput`.

| Exception | When |
|---|---|
| `ExecutableNotFound` | The program is not on the `PATH`, or its path is not an executable file. |
| `TableNotFound` | liblouis cannot find a table it was asked for: its error output says it cannot resolve, find or open it. |
| `LiblouisFailed` | The program stopped with another exit code, ran out of time, did not run to its end, wrote more than its limit, gave back another number of lines or characters that are not braille, or did not name its version; the batch was over its limit. |

`UnsupportedTranslation` of `phil/braille-export` says that no table is set for the language and the contraction.
`InvalidArgumentException` stops a translator or a set of tables built with a value out of its bounds.

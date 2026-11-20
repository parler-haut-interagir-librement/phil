# Reference

Every public type of the package, with its role.

| Type | Role |
|---|---|
| `LiblouisTranslator` | The `Translator` of `phil/braille-export` that runs `lou_translate`; `MINIMUM_VERSION`, the oldest liblouis it runs by default. |
| `LiblouisTables` | The liblouis table of each language and contraction. |
| `Exception\LiblouisException` | Every exception of the translator, with the exit code and the error output. |
| `Exception\ExecutableNotFound` | The program is missing. |
| `Exception\TableNotFound` | A table is missing. |
| `Exception\OutdatedLiblouis` | The program reports a liblouis older than `LiblouisTranslator::MINIMUM_VERSION`. |
| `Exception\LiblouisFailed` | Any other failure. |

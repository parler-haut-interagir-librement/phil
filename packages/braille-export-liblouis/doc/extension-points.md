# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

The package is one adapter: `LiblouisTranslator`, the `Phil\BrailleExport\Contract\Translator` of
`phil/braille-export`. It declares no port of its own.

| To | Do |
|---|---|
| Use it | Give it to `BrailleExporter`, alone or first in a `ChainTranslator`: see [Wiring the service](installation.md#wiring-the-service) |
| Translate another language, or with other tables | `LiblouisTables`, given as `$tables` |
| Use tables kept elsewhere | `$tablePath` |
| Point to another program | `$binary`: a name found on the `PATH`, or a path |
| Change how long a batch may take, or how large it may be | `$timeout`, `$maxInputBytes`, `$maxOutputBytes` |
| Use another display table that writes Unicode braille | `$displayTable` |
| Check the program at deployment | `LiblouisTranslator::isAvailable()` and `version()` |
| Use another translation program | Write another `Translator` |

`LiblouisTranslator` is final: an application decorates it as a service.

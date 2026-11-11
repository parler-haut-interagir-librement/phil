# Extension points

The library is plain PHP: it declares no service and no port. An application extends it through what its own enums
declare.

| Point | How |
|---|---|
| Behaviour on an enum | The traits: the aggregate, or the single traits an enum needs — see [Installation](installation.md) |
| Metadata of the application's own | An attribute class extending `Phil\Enums\Meta\AbstractMetaProperty`, listed in the enum's `#[Meta(...)]`; it may override `transform()`, `defaultValue()` and `customMethodName()` — see [Metadata](metadata.md) |
| Metadata brought by a trait | A trait carrying its own `#[Meta(...)]`: its meta properties are read after the enum's, at run time and by PHPStan |
| Metadata every case must have | `#[RequiredMeta(...)]` on the enum, reported by the PHPStan rule |
| Other names a case is parsed from | `#[Alias(...)]` on the case |
| The case given when none is | `#[DefaultCase]` on one case |
| Which cases follow which | `#[TransitionsTo(...)]` on each case |
| Typing for PHPStan | `extension.neon`, included by hand or automatically through `phpstan/extension-installer` — see [PHPStan](phpstan.md) |

The shipped meta properties — `Label`, `Description`, `Group`, `Weight`, `Alias` — are final: an application that wants
another behaviour writes a meta property of its own rather than extending them.

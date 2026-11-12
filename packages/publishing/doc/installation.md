# Installation

| Requirement | Version | Why |
|---|---|---|
| PHP | 8.5 or later | The floor the library declares and is tested on; the contract declares its fields as interface properties |
| `phil/url` | ^0.1@dev | `Url`, the canonical address of a page, absolute and normalised |

```sh
composer require phil/publishing
```

Until the package is published, it is resolved from the PHIL monorepo through a `path` or `vcs` repository.

The library declares no service and needs no configuration. The events go through the application's own
dispatcher: Symfony's EventDispatcher, or any PSR-14 dispatcher.

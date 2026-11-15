# Reference

| Type | Kind | Role |
|---|---|---|
| `Url` | `final readonly class` | An absolute web address, normalised |
| `UrlType` | interface, for enums | A kind of address and its rule |
| `KnownUrlType` | string-backed enum, `UrlType` | The international kinds |
| `TypedUrl` | `final readonly class` | An address and its kind, checked |
| `UrlTypes` | `final class` | Detecting the kind of an address among enum sets |
| `ServiceUrl` | `final readonly class` | The address of a service on a declared host: a name without a domain or an IP address; `fromString()`, `resolve()`, `origin()` |
| `ServiceHosts` | `final readonly class` | The hosts of the services an application declares, normalised; `declares()`, `all()` |
| `CookielessVideo` | `final readonly class` | The player without cookies of a YouTube or Vimeo video; `fromUrl()` |
| `Exception\UrlException` | interface | Every exception of the library |
| `Exception\InvalidUrl` | `final class`, `InvalidArgumentException` | An address refused, or not of its kind |

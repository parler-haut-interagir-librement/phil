# Reference

| Type | Kind | Role |
|---|---|---|
| `MessengerType` | interface, for enums | A service: reading, showing and linking its handles |
| `KnownMessenger` | string-backed enum, `MessengerType` | The international services |
| `Handle` | `final readonly class` | A handle on a service, canonical: `of()` reads a typed text, `fromCanonical()` takes back a handle kept |
| `Messengers` | `final class` | Detecting the service of an input among enum sets |
| `Exception\InstantMessengerException` | interface | Every exception of the library |
| `Exception\InvalidHandle` | `final class`, `InvalidArgumentException` | An input no accepted service reads |

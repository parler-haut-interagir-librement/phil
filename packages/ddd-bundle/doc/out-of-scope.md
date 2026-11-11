# What the bundle deliberately does not contain

The bundle is the code every bounded context of every application shares. Each addition is a dependency all of
them acquire at once, and a breaking change costs every one of them a major version. It therefore stays
deliberately tiny, and the following are left out on purpose.

| Left out | Why | What to do instead |
|---|---|---|
| **An aggregate base class** | An aggregate is a class of its context. A shared base class melts every context into one as soon as the first useful method is put into it | Write each aggregate as a plain class of its context |
| **A `Clock` interface** | One that merely extended PSR-20 would add nothing but a name | Depend on `Psr\Clock\ClockInterface` directly; `symfony/clock` implements it |
| **Event sourcing** | Events notify, they do not reconstitute: no event store, no replay | Store state as state; see [domain-events.md](domain-events.md) |
| **Messenger bus declarations** | The middleware of each bus (a transaction on commands, none on queries) is the application's decision | Declare the buses in `messenger.yaml`; see [installation.md](installation.md) |
| **A public presentation of identifiers** | `Identifier` is the internal representation; what leaves the system has a form the application decides | Map identifiers to tokens, slugs or opaque references in the application |
| **Business value objects** (an e-mail address, a locale…) | Their normalisation and accepted values are policies of each application | Write them in the application, on `InvalidValue` |
| **A validator in the domain** | A violation list is for forms; a value object either exists or does not | Validate in the value object, throw `InvalidValue` |

## Not started yet

These belong in the bundle and are not in the code today:

- **Dispatching domain events after the Doctrine flush**, inside the handler's transaction and before its commit:
  an interface the aggregate implements to record its events, and a Doctrine listener that releases them on an
  event bus. See [domain-events.md](domain-events.md) for what to do meanwhile.
- **A documented Messenger inbox configuration** for the consumers of integration events, written as
  documentation rather than code.

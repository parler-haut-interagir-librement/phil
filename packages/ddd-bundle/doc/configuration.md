# Configuration reference

The configuration lives under the `phil_ddd` key. It has two keys, both optional.

## Complete reference

```yaml
# config/packages/phil_ddd.yaml
phil_ddd:
    # Service id of the Messenger bus that carries commands. It must be transactional:
    # the application gives it a transaction middleware (doctrine_transaction with Doctrine).
    # Type: string, not empty. Default: command.bus
    command_bus: command.bus

    # Service id of the Messenger bus that carries queries. It must not be transactional.
    # Type: string, not empty. Default: query.bus
    query_bus: query.bus
```

| Key | Type | Default | Effect |
|---|---|---|---|
| `command_bus` | non-empty string, a service id | `command.bus` | The bus `MessengerCommandBus` dispatches to, and the bus every `CommandHandler` is tagged on |
| `query_bus` | non-empty string, a service id | `query.bus` | The bus `MessengerQueryBus` dispatches to, and the bus every `QueryHandler` is tagged on |

Without a `phil_ddd.yaml` file, both defaults apply.

## What the bundle wires

| Service id | Class | Visibility | Argument |
|---|---|---|---|
| `Phil\DddBundle\Infrastructure\Messenger\MessengerCommandBus` | `MessengerCommandBus` | private | a reference to the `command_bus` service |
| `Phil\DddBundle\Application\Bus\CommandBus` | alias of `MessengerCommandBus` | private | |
| `Phil\DddBundle\Infrastructure\Messenger\MessengerQueryBus` | `MessengerQueryBus` | private | a reference to the `query_bus` service |
| `Phil\DddBundle\Application\Bus\QueryBus` | alias of `MessengerQueryBus` | private | |

Inject the interfaces, `CommandBus` and `QueryBus`; autowiring resolves them through the aliases.

The bundle also registers two autoconfiguration rules:

| Interface | Tag added | Tag attribute |
|---|---|---|
| `Phil\DddBundle\Application\Bus\CommandHandler` | `messenger.message_handler` | `bus: <command_bus>` |
| `Phil\DddBundle\Application\Bus\QueryHandler` | `messenger.message_handler` | `bus: <query_bus>` |

The bundle registers no Doctrine type, no event listener and no Messenger bus. Identifier column types are
registered by the application ([doctrine-identifier-type.md](doctrine-identifier-type.md)).

## Errors at build time

| Configuration | Error |
|---|---|
| `command_bus: ''` or `query_bus: ''`, or `~` | `InvalidConfigurationException`: the path `phil_ddd.<key>` cannot contain an empty value |
| A value that is not a string, such as `5` or `true` | `InvalidConfigurationException`: `"phil_ddd.<key>" must be a service id.` |
| Any other key under `phil_ddd` | `InvalidConfigurationException`: unrecognized option |
| A bus id that names no service | Symfony refuses to compile the container: the adapter references a service that does not exist, and Messenger refuses a handler tag that names an unknown bus |

## Example: other bus names

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        default_bus: write.bus
        buses:
            write.bus:
                middleware: [validation, doctrine_transaction]
            read.bus: ~

# config/packages/phil_ddd.yaml
phil_ddd:
    command_bus: write.bus
    query_bus: read.bus
```

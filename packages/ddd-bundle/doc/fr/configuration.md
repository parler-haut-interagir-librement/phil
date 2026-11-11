<!-- phil-dev source-sha256=b5b4ce644ecd4a3e1d5ed1b3c7fddf8971ca2f88e17f8fb08627b44e7111d310 -->
# Référence de la configuration

La configuration se trouve sous la clé `phil_ddd`. Elle a deux clés, toutes deux facultatives.

## Référence complète

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

| Clé | Type | Valeur par défaut | Effet |
|---|---|---|---|
| `command_bus` | chaîne non vide, un identifiant de service | `command.bus` | Le bus vers lequel `MessengerCommandBus` répartit, et le bus sur lequel chaque `CommandHandler` est étiqueté |
| `query_bus` | chaîne non vide, un identifiant de service | `query.bus` | Le bus vers lequel `MessengerQueryBus` répartit, et le bus sur lequel chaque `QueryHandler` est étiqueté |

Sans fichier `phil_ddd.yaml`, les deux valeurs par défaut s'appliquent.

## Ce que le bundle câble

| Identifiant de service | Classe | Visibilité | Argument |
|---|---|---|---|
| `Phil\DddBundle\Infrastructure\Messenger\MessengerCommandBus` | `MessengerCommandBus` | privé | une référence au service `command_bus` |
| `Phil\DddBundle\Application\Bus\CommandBus` | alias de `MessengerCommandBus` | privé | |
| `Phil\DddBundle\Infrastructure\Messenger\MessengerQueryBus` | `MessengerQueryBus` | privé | une référence au service `query_bus` |
| `Phil\DddBundle\Application\Bus\QueryBus` | alias de `MessengerQueryBus` | privé | |

Injectez les interfaces, `CommandBus` et `QueryBus` ; l'autoconfiguration des arguments (*autowiring*) les résout par
les alias.

Le bundle enregistre aussi deux règles d'autoconfiguration :

| Interface | Étiquette ajoutée | Attribut de l'étiquette |
|---|---|---|
| `Phil\DddBundle\Application\Bus\CommandHandler` | `messenger.message_handler` | `bus: <command_bus>` |
| `Phil\DddBundle\Application\Bus\QueryHandler` | `messenger.message_handler` | `bus: <query_bus>` |

Le bundle n'enregistre aucun type Doctrine, aucun écouteur d'événements et aucun bus Messenger. Les types de colonne
des identifiants sont enregistrés par l'application ([doctrine-identifier-type.md](doctrine-identifier-type.md)).

## Erreurs à la construction

| Configuration | Erreur |
|---|---|
| `command_bus: ''` ou `query_bus: ''`, ou `~` | `InvalidConfigurationException` : le chemin `phil_ddd.<key>` ne peut pas contenir de valeur vide |
| Une valeur qui n'est pas une chaîne, comme `5` ou `true` | `InvalidConfigurationException` : `"phil_ddd.<key>" must be a service id.` |
| Toute autre clé sous `phil_ddd` | `InvalidConfigurationException` : option non reconnue |
| Un identifiant de bus qui ne nomme aucun service | Symfony refuse de compiler le conteneur : l'adaptateur référence un service qui n'existe pas, et Messenger refuse une étiquette de *handler* qui nomme un bus inconnu |

## Exemple : d'autres noms de bus

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

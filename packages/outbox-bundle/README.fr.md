<!-- phil-dev source-sha256=1bb39dcd6c6ad8331ef35ee22885b46e2abae7271f4d1337c6604e01ea0fbafc -->
# phil/outbox-bundle

[![OpenSSF Scorecard](https://api.scorecard.dev/projects/github.com/parler-haut-interagir-librement/outbox-bundle/badge)](https://scorecard.dev/viewer/?uri=github.com/parler-haut-interagir-librement/outbox-bundle)

**phil/outbox dans Symfony : les événements enregistrés dans une table de votre connexion Doctrine, livrés par une
commande ou par le Scheduler, avec de nouveaux essais et un pont vers Messenger.**

phil/outbox-bundle intègre [phil/outbox](https://github.com/parler-haut-interagir-librement/outbox) à Symfony. Les
bibliothèques qui enregistrent des événements par l'outbox restent libres de Symfony et de Messenger ; le bundle
conserve leurs enregistrements à côté de leurs tables et les relaie aux écouteurs de l'application. La bibliothèque
seule convient à une application sans Symfony.

## État

| Partie | État |
|---|---|
| `DbalOutboxStore` : une table de la connexion de l'application, jointe à la transaction de l'appelant, testée sur SQLite, MySQL 8.4 et PostgreSQL 17 | Implémenté et testé |
| La table dans le schéma que l'ORM génère, et `phil:outbox:schema` | Implémentées et testées |
| Les codecs par étiquette, les types d'événement génériques, l'outbox, le relais et l'entretien comme services | Implémentés et testés |
| `phil:outbox:relay` : lots bornés, `--stop-when-empty`, une limite de temps, un verrou, les signaux | Implémentée et testée |
| Les commandes des enregistrements morts et la purge des enregistrements livrés | Implémentées et testées |
| Le déclencheur planifié et le pont vers un bus de Messenger | Implémentés et testés |
| La recette Flex | Implémentée |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Prérequis, installation et création de la table. |
| [Configuration](doc/fr/configuration.md) | Chaque option, avec un exemple. |
| [Console](doc/fr/console.md) | La commande de relais et les commandes des enregistrements morts, de la purge et du schéma. |
| [Workers](doc/fr/workers.md) | Exécuter le relais sous un gestionnaire de processus, en mode *worker*, ou selon un horaire. |
| [Messenger](doc/fr/messenger.md) | Le pont vers un bus, et quand l'`outbox` et l'`inbox` propres à Messenger conviennent mieux. |
| [Points d'extension](doc/fr/extension-points.md) | Les services à remplacer, les étiquettes à ajouter, et quand c'est stable. |
| [Tests](doc/fr/testing.md) | Les tests du bundle, et les serveurs que lisent ses tests d'intégration. |
| [Référence](doc/fr/reference.md) | Chaque classe et chaque service du bundle. |

## Démarrage rapide

```sh
composer require phil/outbox-bundle
php bin/console phil:outbox:schema --force   # or doctrine:migrations:diff with the ORM
```

```yaml
# config/packages/phil_outbox.yaml
phil_outbox:
    connection: default
    generic_types: [quiz.completed]
```

```php
// In the transaction of the write it describes:
$outbox->record('quiz.completed', ['quiz' => 'q-42', 'member' => 7]);
```

```sh
php bin/console phil:outbox:relay
```

## Prérequis

PHP 8.5 ou plus récent, Symfony 8.2 ou plus récent et Doctrine DBAL 4.5 ou plus récent.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).

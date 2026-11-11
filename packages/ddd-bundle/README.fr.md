<!-- phil-dev source-sha256=418630e7cc39a96fd5a707514d6e5606c39b7796bc1d6328a3280a8786843ea5 -->
# phil/ddd-bundle

**Les quelques types que partagent tous les contextes délimités : identifiants, événements du domaine, erreurs, bus de
commandes et de requêtes.**

phil/ddd-bundle est le noyau DDD/CQRS d'une application Symfony découpée en contextes délimités. Il fournit des
identifiants d'agrégat UUIDv7 que l'appelant génère, une interface d'événement du domaine, des erreurs du domaine, et
des ports de bus de commandes et de requêtes avec leurs adaptateurs Messenger. Les *handlers* sont autoconfigurés sur
le bon bus. Un type de colonne Doctrine DBAL facultatif stocke les identifiants dans une colonne `uuid` native, ou en
`BINARY(16)` quand la plateforme n'en a pas. Le bundle ne déclare pas vos bus Messenger. Il ne fournit ni classe de
base d'agrégat, ni interface d'horloge, ni *event sourcing*, ni objets-valeurs métier, ni présentation publique des
identifiants.

Il s'adresse aux développeurs qui écrivent une application Symfony en plusieurs contextes et qui veulent leur faire
partager un vocabulaire petit et stable. Le noyau reste volontairement minuscule : chaque ajout est une dépendance que
tous les contextes acquièrent d'un coup, et un changement incompatible coûte une version majeure à chacun d'eux.
D'autres bundles phil/* qui ont besoin d'identifiants ou d'erreurs du domaine s'appuient sur lui, comme
`phil/audit-bundle` et `phil/tenancy-bundle`. Une application à un seul contexte, faite de simples services, en a peu
l'usage.

C'est un bundle Symfony sur Messenger et le composant Uid, avec Doctrine DBAL en option. La conception suit les ports
et adaptateurs : les couches du domaine et de l'application ne voient que `CommandBus` et `QueryBus`, et les classes
Messenger se trouvent derrière eux, dans la couche d'infrastructure.

## État

| Partie | État |
|---|---|
| `Identifier`, `DomainEvent`, `DomainError`, `InvalidValue` | Implémenté et testé |
| `Command`, `Query`, `CommandBus`, `QueryBus`, `CommandHandler`, `QueryHandler` | Implémenté et testé |
| `MessengerCommandBus`, `MessengerQueryBus`, `PhilDddBundle` (configuration `phil_ddd`) | Implémenté et testé |
| `IdentifierType` (DBAL, facultatif) | Implémenté ; tests unitaires sur cinq plateformes, tests d'intégration sur MySQL 8 et PostgreSQL |
| Répartition des événements du domaine après le *flush* de Doctrine, dans la transaction | Non commencé |
| Configuration documentée d'une boîte de réception Messenger | Non commencé |

## Documentation

- [Installation](doc/fr/installation.md) : prérequis, installation, enregistrement du bundle, et les deux bus Messenger
  que déclare l'application.
- [Configuration](doc/fr/configuration.md) : les clés `phil_ddd` avec leurs valeurs par défaut, les services et les
  règles d'autoconfiguration qu'enregistre le bundle, et les erreurs levées à la construction.
- [Identifiants](doc/fr/identifiers.md) : `Identifier` et ses règles UUIDv7, pourquoi l'appelant le génère, et
  l'égalité par classe et par valeur.
- [Événements du domaine](doc/fr/domain-events.md) : l'interface `DomainEvent`, ses règles, et ce qui n'est pas encore
  implémenté pour répartir les événements.
- [Erreurs du domaine](doc/fr/errors.md) : `DomainError` et `InvalidValue`, et la façon dont les objets-valeurs se
  valident eux-mêmes.
- [Bus de commandes et de requêtes](doc/fr/command-and-query-buses.md) : commandes, requêtes, *handlers*, pourquoi les
  marqueurs décident du bus, comment sortent les échecs, et les messages sur un transport.
- [Type d'identifiant Doctrine](doc/fr/doctrine-identifier-type.md) : `IdentifierType`, la colonne de chaque
  plateforme, les conversions et leurs erreurs.
- [Hors du périmètre](doc/fr/out-of-scope.md) : ce que le bundle laisse de côté volontairement, et ce qui n'est pas
  encore commencé.
- [Points d'extension](doc/fr/extension-points.md) : chaque classe et interface que l'application étend ou implémente,
  avec son contrat et un exemple.
- [Tests](doc/fr/testing.md) : les suites unitaires et d'intégration du bundle, leurs variables d'environnement, et la
  façon dont une application teste ses *handlers* et son câblage.
- [Référence](doc/fr/reference.md) : l'API publique par couche, une ligne par type.

## Démarrage rapide

```bash
composer require phil/ddd-bundle
```

```php
// config/bundles.php
Phil\DddBundle\PhilDddBundle::class => ['all' => true],
```

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        buses:
            command.bus:
                middleware: [validation, doctrine_transaction]
            query.bus: ~
```

```php
final readonly class OrderId extends Identifier {}

final readonly class PlaceOrder implements Command
{
    public function __construct(public string $orderId) {}
}

final readonly class PlaceOrderHandler implements CommandHandler
{
    public function __invoke(PlaceOrder $command): void { /* … */ }
}

$orderId = OrderId::generate();
$commandBus->dispatch(new PlaceOrder($orderId->toString()));
```

## Prérequis

PHP 8.5 ou plus récent ; `symfony/config`, `symfony/dependency-injection`, `symfony/messenger` et `symfony/uid` 8.2 ou
plus récents ; `doctrine/dbal` 4.2 ou plus récent seulement pour le type de colonne des identifiants.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la [politique de
sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).

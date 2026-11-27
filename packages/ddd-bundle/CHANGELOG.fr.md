<!-- phil-dev source-sha256=f27731050f7ace14ef9c5523468a2020ec8ea5436455fb42c68085e8fe630b80 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/ddd-bundle` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## Non publié

La prochaine version est la 0.2.1-alpha.

Documentation seulement.

### Modifié

- La documentation des bus dit comment les échecs sortent sur un bus de Messenger avec `unwrap_exceptions` : l'erreur de
  domaine d'une commande et l'échec d'une requête sortent de la même façon, et l'autre échec d'une commande déballé.
- La documentation des bus dit comment renommer une commande en file avec le `serializedTypeNameAliases` de Messenger.

## 0.2.0-alpha - 2026-11-26

### Ajouté

- `IdentifierValueResolver` : un argument de contrôleur typé avec une classe d'identifiant reçoit l'attribut de requête
  du même nom, un paramètre de route, comme instance de cette classe ; toute autre valeur est une 404. Enregistré quand
  `symfony/http-kernel` est installé, sous son nom de classe, pour que
  `#[ValueResolver(IdentifierValueResolver::class, disabled: true)]` en exclue un argument, en mode débogage aussi.
- `IdentifierNormalizer` : le Serializer écrit un identifiant comme sa chaîne canonique et le reconstruit dans la classe
  demandée ; toute autre valeur est une `NotNormalizableValueException` dont le message peut être montré à l'usager.
  Enregistré quand `symfony/serializer` est installé.
- `symfony/http-kernel` et `symfony/serializer` dans `suggest`, avec un `conflict` sur les versions sous 8.2.
- `make:ddd:context`, `make:ddd:identifier`, `make:ddd:command`, `make:ddd:query` et `make:ddd:event` : avec
  `symfony/maker-bundle`, ils écrivent un contexte délimité (`src/<Context>/{Domain,Application,Infrastructure}` et un
  README), un identifiant et son type Doctrine, une commande ou une requête avec son handler, et un événement de
  domaine, jamais par-dessus un fichier existant. `DddSkeleton` rend les fichiers à partir des gabarits de
  `templates/maker/`. Enregistrés quand MakerBundle est installé ; `symfony/maker-bundle` dans `suggest`, avec un
  `conflict` sur les versions sous 1.68.

### Modifié

- Avec `symfony/serializer` installé, un identifiant est sérialisé comme sa chaîne au lieu de `{"value": …}`.
- Les prérequis de développement demandent la dernière mineure de chaque dépendance (`doctrine/dbal` 4.6).

## 0.1.1-alpha - 2026-11-17

Documentation seulement.

### Modifié

- `CONTRIBUTING.md` dit ce que fait le paquet et comment y travailler, sans la formulation de ses premières ébauches.

## 0.1.0-alpha - 2026-11-11

### Ajouté

- `Identifier` (`generate()`, `fromString()`, `fromUuid()`), `DomainEvent`, `DomainError`, `InvalidValue`.
- `Command`, `Query`, `CommandBus`, `QueryBus`, `CommandHandler`, `QueryHandler`.
- `IdentifierType` (DBAL, facultatif), testé unitairement sur cinq plateformes, testé en intégration sur MySQL 8 et
  PostgreSQL. Il détermine si une plateforme a un type UUID natif une fois par objet de plateforme, dans une `WeakMap`,
  et construit un identifiant lu d'une colonne binaire à partir de l'UUID lu, par `Identifier::fromUuid()`.
- `PhilDddBundle` (configuration `phil_ddd`) ; `MessengerCommandBus`, `MessengerQueryBus`.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.

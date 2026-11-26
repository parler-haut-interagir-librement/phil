<!-- phil-dev source-sha256=c7151162e8504f5fc56145662369d1a51493fffac847345f9f9cc7aca7891767 -->
# Référence

Chaque type public du bundle, par couche. Tous sont implémentés et testés, sauf si l'état dit autre chose.

## `Phil\DddBundle\Domain`

Ne dépend que de `symfony/uid`.

| Type | Nature | Rôle | État |
|---|---|---|---|
| `Identifier\Identifier` | `abstract readonly class`, `Stringable` | Base des identifiants d'agrégat : UUIDv7, `generate()`, `fromString()`, `equals()` par classe et par valeur, `toString()` | Implémenté |
| `Event\DomainEvent` | interface | Un fait passé dans un contexte : `occurredAt()` | Implémenté |
| `Exception\DomainError` | interface, étend `Throwable` | Ce que lève le domaine | Implémenté |
| `Exception\InvalidValue` | `final class`, `InvalidArgumentException` et `DomainError` | Un objet-valeur a refusé sa construction : `because(string $reason)` | Implémenté |

## `Phil\DddBundle\Application`

| Type | Nature | Rôle | État |
|---|---|---|---|
| `Bus\Command` | interface marqueur | Une intention de changer l'état ; ne retourne rien | Implémenté |
| `Bus\Query` | interface marqueur, `@template TResult` | Une question posée au modèle de lecture | Implémenté |
| `Bus\CommandBus` | port | `dispatch(Command): void`, lève `DomainError` | Implémenté |
| `Bus\QueryBus` | port | `ask(Query): mixed`, typé `TResult` | Implémenté |
| `Bus\CommandHandler` | marqueur d'autoconfiguration | Étiquette le *handler* sur le bus de commandes | Implémenté |
| `Bus\QueryHandler` | marqueur d'autoconfiguration | Étiquette le *handler* sur le bus de requêtes | Implémenté |
| `Maker\DddSkeleton` | `final readonly class` | Produit les fichiers des commandes `make:ddd:*` à partir de `templates/maker/`, sans les écrire ; refuse un nom qui n'est pas un mot en PascalCase | Implémenté |
| `Maker\SkeletonFile` | `final readonly class` | Un fichier produit : son chemin, relatif au projet, et son contenu | Implémenté |

## `Phil\DddBundle\Infrastructure`

| Type | Nature | Rôle | État |
|---|---|---|---|
| `Messenger\MessengerCommandBus` | `final readonly class`, `CommandBus` | Répartit sur le bus Messenger configuré ; désenveloppe la première `DomainError` | Implémenté |
| `Messenger\MessengerQueryBus` | `final class`, `QueryBus` | Répartit sur le bus Messenger configuré ; retourne le résultat de l'unique *handler* ; désenveloppe tout échec du *handler* | Implémenté |
| `Doctrine\IdentifierType` | `abstract class`, `Type` DBAL, `@template T of Identifier` | Type de colonne d'une classe d'identifiant : UUID natif ou binaire de 16 octets | Implémenté ; facultatif, exige `doctrine/dbal` 4.2 ou plus récent |
| `HttpKernel\IdentifierValueResolver` | `final readonly class`, `ValueResolverInterface` | Transforme l'attribut de requête nommé comme un argument de contrôleur en la classe d'identifiant que celui-ci déclare ; 404 pour toute autre valeur | Implémenté ; enregistré quand `symfony/http-kernel` est installé |
| `Serializer\IdentifierNormalizer` | `final readonly class`, `NormalizerInterface`, `DenormalizerInterface` | Un identifiant sous sa forme canonique de chaîne, reconstruit sous la classe demandée ; `NotNormalizableValueException` pour toute autre valeur | Implémenté ; enregistré quand `symfony/serializer` est installé |
| `Maker\MakeDddContext`, `Maker\MakeDddIdentifier`, `Maker\MakeDddCommand`, `Maker\MakeDddQuery`, `Maker\MakeDddEvent` | `final class`, `AbstractMaker` de MakerBundle par `Maker\AbstractDddMaker` | Les commandes `make:ddd:*` : écrivent ce que produit `DddSkeleton`, jamais par-dessus un fichier existant | Implémenté ; enregistré quand `symfony/maker-bundle` est installé |

## Le bundle

| Type | Nature | Rôle | État |
|---|---|---|---|
| `PhilDddBundle` | `final class`, `AbstractBundle` | Alias de configuration `phil_ddd` ; câble les deux adaptateurs, leurs alias, l'autoconfiguration des marqueurs, le résolveur et le normaliseur des identifiants, et les commandes `make:ddd:*`, quand leurs paquets sont installés | Implémenté |

## Pas encore dans le code

| Partie | État |
|---|---|
| Enregistrement des événements du domaine sur les agrégats et leur répartition après le *flush*, dans la transaction | Non commencé |
| Configuration documentée d'une boîte de réception Messenger pour les consommateurs d'événements d'intégration | Non commencé |

Voir [configuration.md](configuration.md) pour les services et les étiquettes, et [out-of-scope.md](out-of-scope.md)
pour ce que le bundle ne contiendra pas.

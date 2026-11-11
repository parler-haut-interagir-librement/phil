<!-- phil-dev source-sha256=451f19bf678081c344d2b699e0b969050527940002098f39f77c46d56d63f8a7 -->
# Le type d'identifiant Doctrine

`Phil\DddBundle\Infrastructure\Doctrine\IdentifierType` est le type DBAL abstrait d'un identifiant : une classe
d'identifiant concrète par colonne, stockée sur 16 octets sur toutes les plateformes.

**Facultatif.** `doctrine/dbal` est un `suggest` du bundle, avec un `conflict` sous 4.2. Rien dans le bundle ne charge
`Infrastructure\Doctrine` de lui-même : une application sans Doctrine n'y touche jamais, et une application qui en
enregistre un type exige déjà DBAL.

## Déclarer un type

Une sous-classe par classe d'identifiant, qui nomme la classe vers laquelle la colonne convertit :

```php
namespace App\Ordering\Infrastructure\Doctrine\Type;

use App\Ordering\Domain\OrderId;
use Phil\DddBundle\Infrastructure\Doctrine\IdentifierType;

/** @extends IdentifierType<OrderId> */
final class OrderIdType extends IdentifierType
{
    protected function identifierClass(): string
    {
        return OrderId::class;
    }
}
```

Enregistrez-la sous un nom de type de votre choix. Avec DoctrineBundle :

```yaml
# config/packages/doctrine.yaml
doctrine:
    dbal:
        types:
            order_id: App\Ordering\Infrastructure\Doctrine\Type\OrderIdType
```

Sans DoctrineBundle, enregistrez-la une fois avant la première connexion :

```php
Doctrine\DBAL\Types\Type::addType('order_id', OrderIdType::class);
```

Associez la colonne :

```php
#[ORM\Id]
#[ORM\Column(type: 'order_id')]
private OrderId $id;

#[ORM\Column(type: 'order_id', nullable: true)]
private ?OrderId $parentId = null;
```

## La colonne, plateforme par plateforme

| Plateforme | Colonne | Valeur envoyée et lue |
|---|---|---|
| PostgreSQL | `UUID` | la chaîne canonique, `0192f3c4-…` |
| SQL Server | `UNIQUEIDENTIFIER` | la chaîne canonique |
| MySQL 8, MariaDB | `BINARY(16)` | les 16 octets bruts |
| SQLite | `BLOB` | les 16 octets bruts |

Une plateforme compte comme native quand sa déclaration de GUID est autre chose que le `CHAR(36)` de repli qu'utilise
DBAL pour les plateformes sans type UUID. Sur les autres, la colonne est un binaire fixe de 16 octets, quelle que soit
la `length` que demande l'association.

- **Jamais de `CHAR(36)`.** Trente-six caractères comparés comme du texte gaspillent de l'espace et des pages d'index.
  Les octets bruts d'un UUIDv7 gardent son ordre temporel dans un index octet par octet : les insertions s'ajoutent
  donc à l'index au lieu de le scinder.
- **Le SQL brut sur une plateforme binaire compare des octets.** Dans une requête écrite à la main sur MySQL 8,
  comparez la colonne avec `UUID_TO_BIN(?)`, pas avec la chaîne. Un paramètre DQL ou DBAL passe par le type quand le
  type est nommé (`$connection->fetchAssociative($sql, [$id], [new OrderIdType()])`, ou le nom du type dans
  `setParameter()` en DQL).
- **Changer de plateforme change la colonne.** Déplacer un schéma de PostgreSQL vers MySQL, ou l'inverse, est une
  migration de données (`uuid` vers `BINARY(16)`), pas un changement de configuration.
- **Les différences de schéma sont stables.** Une colonne relue par introspection de la base déclare le même SQL que le
  type qui l'a créée : une différence de migration ne trouve donc rien à changer (testé sur MySQL 8 et PostgreSQL).

## Conversions

| Sens | Entrée | Résultat |
|---|---|---|
| Vers la base | `null` | `null` |
| Vers la base | un identifiant exactement de la classe que nomme `identifierClass()` | la chaîne canonique sur une plateforme native, 16 octets bruts sinon |
| Vers la base | un identifiant d'une autre classe, une chaîne UUID, des octets bruts, toute autre valeur | `Doctrine\DBAL\Types\Exception\InvalidType` |
| Vers PHP | `null` | `null` |
| Vers PHP | une chaîne qui contient un UUIDv7 sous la forme de la colonne | une instance de la classe d'identifiant de la colonne |
| Vers PHP | une valeur qui n'est pas une chaîne | `ValueNotConvertible`, qui nomme le type PHP |
| Vers PHP | un UUID d'une autre version, ou un texte qui n'est pas un UUID | `ValueNotConvertible`, avec l'`InvalidValue` du domaine comme exception précédente |
| Vers PHP, plateforme binaire | tout ce qui ne fait pas exactement 16 octets | `ValueNotConvertible`, dont le message donne la longueur (`"15 bytes"`), jamais la valeur brute |

## Choix de conception

- **Un type par classe d'identifiant, pas un type pour toutes.** Un type partagé relirait chaque colonne comme la même
  classe, et un `OrderId` pourrait sortir d'une colonne `customer_id` sans qu'on le remarque. Ici, la colonne décide de
  la classe : une valeur d'une autre classe d'identifiant est refusée à l'entrée même si elle porte un UUIDv7 valide,
  et la vérification porte sur la classe exacte, comme la lit `Identifier::equals()`.
- **Les chaînes sont refusées à l'entrée.** Seuls des objets identifiants sont stockés, pour qu'un paramètre ne puisse
  pas contourner la vérification de la classe.
- **Une valeur relue doit être un UUIDv7.** Le refus est une erreur de conversion DBAL plutôt qu'une erreur du
  domaine : il dit que la colonne contient une valeur qu'aucun code de l'application n'a écrite.
- **Référence de conception.** Le choix entre natif et binaire suit le modèle d'`AbstractUidType`, du pont Doctrine de
  Symfony. Contrairement à lui, ce type est lié à une seule classe d'identifiant, n'accepte aucune chaîne et refuse tout
  UUID qui n'est pas un v7.

## API publique

| Membre | Visibilité | Rôle |
|---|---|---|
| `identifierClass(): string` | `abstract protected` | La seule méthode qu'écrit une sous-classe : `class-string<T>` de l'identifiant |
| `getSQLDeclaration(array $column, AbstractPlatform $platform): string` | publique | La déclaration GUID native, ou un binaire fixe de 16 octets |
| `convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string` | publique | Comme dans le tableau des conversions |
| `convertToPHPValue(mixed $value, AbstractPlatform $platform): ?Identifier` | publique | Comme dans le tableau des conversions, typée `?T` pour l'analyse statique |

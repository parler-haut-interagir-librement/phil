# The Doctrine identifier type

`Phil\DddBundle\Infrastructure\Doctrine\IdentifierType` is the abstract DBAL type of an identifier: one concrete
identifier class per column, stored in 16 bytes on every platform.

**Optional.** `doctrine/dbal` is a `suggest` of the bundle, with a `conflict` below 4.2. Nothing in the bundle loads
`Infrastructure\Doctrine` on its own: an application without Doctrine never touches it, and an application that
registers a type from it already requires DBAL.

## Declaring a type

One subclass per identifier class, naming the class the column converts to:

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

Register it under a type name of your choosing. With DoctrineBundle:

```yaml
# config/packages/doctrine.yaml
doctrine:
    dbal:
        types:
            order_id: App\Ordering\Infrastructure\Doctrine\Type\OrderIdType
```

Without DoctrineBundle, register it once before the first connection:

```php
Doctrine\DBAL\Types\Type::addType('order_id', OrderIdType::class);
```

Map the column:

```php
#[ORM\Id]
#[ORM\Column(type: 'order_id')]
private OrderId $id;

#[ORM\Column(type: 'order_id', nullable: true)]
private ?OrderId $parentId = null;
```

## The column, platform by platform

| Platform | Column | Value sent and read |
|---|---|---|
| PostgreSQL | `UUID` | the canonical string, `0192f3c4-…` |
| SQL Server | `UNIQUEIDENTIFIER` | the canonical string |
| MySQL 8, MariaDB | `BINARY(16)` | the 16 raw bytes |
| SQLite | `BLOB` | the 16 raw bytes |

A platform counts as native when its GUID declaration is anything but the `CHAR(36)` fallback DBAL uses for
platforms without a UUID type. On the others, the column is a fixed 16-byte binary, whatever `length` the mapping
asks for.

- **Never a `CHAR(36)`.** Thirty-six characters compared as text waste space and index pages. The raw bytes of a
  UUIDv7 keep its time order in a byte-wise index, so inserts append to the index instead of splitting it.
- **Raw SQL on a binary platform compares bytes.** In a hand-written query on MySQL 8, compare the column with
  `UUID_TO_BIN(?)`, not with the string. A DQL or DBAL parameter goes through the type when the type is named
  (`$connection->fetchAssociative($sql, [$id], [new OrderIdType()])`, or the type name in DQL `setParameter()`).
- **Changing platform changes the column.** Moving a schema from PostgreSQL to MySQL, or back, is a data migration
  (`uuid` to `BINARY(16)`), not a configuration change.
- **Schema diffs are stable.** A column introspected back from the database declares the same SQL as the type that
  created it, so a migration diff finds nothing to change (tested against MySQL 8 and PostgreSQL).

## Conversions

| Direction | Input | Result |
|---|---|---|
| To the database | `null` | `null` |
| To the database | an identifier of exactly the class `identifierClass()` names | the canonical string on a native platform, 16 raw bytes otherwise |
| To the database | an identifier of another class, a UUID string, raw bytes, any other value | `Doctrine\DBAL\Types\Exception\InvalidType` |
| To PHP | `null` | `null` |
| To PHP | a string holding a UUIDv7 in the column's form | an instance of the column's identifier class |
| To PHP | a value that is not a string | `ValueNotConvertible` naming the PHP type |
| To PHP | a UUID of another version, or text that is not a UUID | `ValueNotConvertible`, with the domain's `InvalidValue` as previous exception |
| To PHP, binary platform | anything but exactly 16 bytes | `ValueNotConvertible` whose message gives the length (`"15 bytes"`), never the raw value |

## Design decisions

- **One type per identifier class, not one type for all.** A shared type would read every column back as the same
  class, and an `OrderId` could come out of a `customer_id` column unnoticed. Here the column decides the class:
  a value of another identifier class is refused on the way in even when it carries a valid UUIDv7, and the
  check is on the exact class, as `Identifier::equals()` reads it.
- **Strings are refused on the way in.** Only identifier objects are stored, so that a parameter cannot bypass the
  class check.
- **A value read back must be a UUIDv7.** The refusal is a DBAL conversion error rather than a domain error: it
  says the column holds a value no code of the application wrote.
- **Design reference.** The native-or-binary choice follows the model of `AbstractUidType` from Symfony's Doctrine
  bridge. Unlike it, this type is bound to one identifier class, accepts no string, and refuses any UUID that is
  not a v7.

## Public API

| Member | Visibility | Role |
|---|---|---|
| `identifierClass(): string` | `abstract protected` | The one method a subclass writes: `class-string<T>` of the identifier |
| `getSQLDeclaration(array $column, AbstractPlatform $platform): string` | public | The native GUID declaration, or a fixed 16-byte binary |
| `convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string` | public | As in the conversion table |
| `convertToPHPValue(mixed $value, AbstractPlatform $platform): ?Identifier` | public | As in the conversion table, typed `?T` for static analysis |

# Doctrine

Doctrine maps a single case with `enumType`. A set of cases needs a type of its own: the bundle registers one DBAL
type per name the configuration declares, when `doctrine/dbal` is installed.

```yaml
# config/packages/phil_enums.yaml
phil_enums:
    doctrine:
        sets:
            permissions: { enum: App\Security\Permission, storage: bitmask }
            channels: { enum: App\Notification\Channel, storage: json }
```

```php
use Doctrine\ORM\Mapping as ORM;
use Phil\Enums\Collection\EnumSet;

#[ORM\Entity]
class Member
{
    /** @var EnumSet<Permission> */
    #[ORM\Column(type: 'permissions')]
    private EnumSet $permissions;

    /** @var EnumSet<Channel>|null */
    #[ORM\Column(type: 'channels', nullable: true)]
    private ?EnumSet $channels = null;
}
```

| Storage | Column | Stored value | Needs |
|---|---|---|---|
| `bitmask` | integer | The values of the cases, or'ed: `5` for `Read` and `Delete`. | An enum backed by integers that are powers of two — the bit flags of `phil/enums`'s `EnumSet`. |
| `json` | JSON | The list of the values, the names of a pure enum: `["email","sms"]`. | Any enum. |

- **`null` stays `null`**, both ways.
- **Writing a value that is not an `EnumSet` of the type's enum fails**, as DBAL's `InvalidType`.
- **Reading a stored value that names no case fails**, as DBAL's `ValueNotConvertible`: a bit no case stands for,
  a value no case has, JSON that is not a list. A set is never read with a case silently dropped.
- **The types are registered once per process**, when the bundle boots; a type name already in DBAL's registry is
  left as it is.

**Never renumber a flag, nor change a value**, once sets are stored: the values are the storage format. A case
added to the enum is safe; a case removed makes the rows that hold it unreadable until they are migrated.

**Check the stored values before a change ships**: `bin/console phil:enums:check-sets` reads every distinct value
of every `EnumSet` column the entities map, and lists those that no longer name cases — the rows that would fail to
load. Run it against a copy of production before deploying a change to such an enum, and after the migration that
rewrites the rows; it fails while it lists any.

Querying a bitmask column — the members who may delete — uses DQL's own `BIT_AND()`, which needs no extension:

```php
$em->createQuery('SELECT m FROM App\Member m WHERE BIT_AND(m.permissions, :delete) > 0')
    ->setParameter('delete', Permission::Delete->value);
```

A JSON column is queried with the database's JSON functions.

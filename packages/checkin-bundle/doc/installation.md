# Installation

## Requirements

| Package | Version | Why |
|---|---|---|
| PHP | 8.5 or later | The version of `phil/checkin`. |
| `phil/checkin` | `^0.1` | The desk, the policy, the offline manifests and synchronisation. |
| `doctrine/dbal` | `^4.5` | The ledger's table. |
| `symfony/security-core`, `symfony/security-csrf` | `^8.2` | The authorization of each checkpoint and the stateless CSRF token. |
| `symfony/routing`, `symfony/asset-mapper`, `symfony/stimulus-bundle` | `^8.2`, `^3.7` | Suggested: the endpoints, the controller served without a build step. |
| `doctrine/orm` | `^3.7` | Suggested: the table in the schema the ORM generates and its migrations. |

## Registering the bundle

```sh
composer require phil/checkin-bundle
```

```php
// config/bundles.php
return [
    // ...
    Phil\CheckinBundle\PhilCheckinBundle::class => ['all' => true],
];
```

FrameworkBundle's CSRF protection stays on: the bundle adds its token to the stateless ones, which need no session.

## The routes

```yaml
# config/routes/phil_checkin.yaml
phil_checkin:
    resource: '@PhilCheckinBundle/config/routes.php'
    prefix: /_checkin
```

Put the prefix behind the firewall that signs the staff in. The endpoints answer 404 until `enabled: true`.

## The table

With the ORM, `doctrine:migrations:diff` writes the migration of the table `phil_checkin_scan`. Without it,
`DoctrineCheckInLedger::configureSchema()` adds the table to a DBAL schema the application migrates itself.

### Collation of the keys

**Keys compare byte by byte**, as PHP compares them. The attendance of a credential is folded from its rows: that of
the credential `Badge-7` is never folded with the rows of `badge-7`, a scan identifier sent in another case is another
scan, and two credentials that differ only by case each take their own sequence.

| Table | Columns in `utf8mb4_bin` on MySQL and MariaDB |
|---|---|
| `phil_checkin_scan` | `id`, `credential_id`, `checkpoint`, `device` |

On MySQL and MariaDB, these columns take the `utf8mb4` character set and the `utf8mb4_bin` collation, given the
connection's platform: the default collation of MySQL 8, `utf8mb4_0900_ai_ci`, folds case and accents. PostgreSQL's
default collations are deterministic, equal only when the bytes are, and SQLite compares bytes: their columns keep the
default.

#### Upgrading from 0.1

The tables of a 0.1 install on MySQL or MariaDB kept the connection's collation. `doctrine:migrations:diff` writes the
migration that brings the columns above to `utf8mb4_bin`; run it with `doctrine:migrations:migrate`. The change only
makes values more distinct, so no existing row breaks a key. Rows that a case-insensitive collation had already merged
stay merged; values written from the migration on are kept apart. On PostgreSQL and SQLite the diff is empty.

## The security

The endpoints ask the application's security whether the user may check in at a checkpoint, with the attribute
`PHIL_CHECKIN_SCAN` and the `CheckpointId` as subject. Without a voter that grants it, nobody may:

```php
use Phil\Checkin\CheckpointId;
use Phil\CheckinBundle\PhilCheckinBundle;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/** @extends Voter<string, CheckpointId> */
final class DoorStaffVoter extends Voter
{
    protected function supports(string $attribute, mixed $subject): bool
    {
        return PhilCheckinBundle::SCAN === $attribute && $subject instanceof CheckpointId;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        return $token->getUser() instanceof Volunteer && $token->getUser()->staffs($subject->value);
    }
}
```

## The page of the desk

The page renders the controller with the configuration of its checkpoint; see [JavaScript](javascript.md).

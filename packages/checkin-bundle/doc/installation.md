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

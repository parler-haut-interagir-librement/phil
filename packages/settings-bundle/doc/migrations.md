# Migrations

A setting's declaration evolves: its key is renamed, its type changes, a choice is renamed, its default moves. The
values already stored follow it through a migration, a versioned change applied once by a command, with the record of
its version.

## Declaring a migration

A migration is a class implementing `Domain\Contract\SettingMigration`, declared with `#[AsSettingMigration]` on an
autoconfigured service:

```php
use Phil\SettingsBundle\Domain\Attribute\AsSettingMigration;
use Phil\SettingsBundle\Domain\Contract\SettingMigration;
use Phil\SettingsBundle\Domain\Migration\Step;

#[AsSettingMigration('2026-11-12.signature')]
final readonly class SignatureRenamed implements SettingMigration
{
    public function steps(): array
    {
        return [
            Step::rename('author.sig', 'author.signature'),
            Step::convert('author.layout', static fn (mixed $old): mixed => true === $old ? 'grid' : 'list'),
            Step::defaultChanged('author.columns', previous: 2),
            Step::remove('author.legacy'),
        ];
    }
}
```

The version is letters, digits, `.`, `_` and `-`, at most 190 characters; two migrations of the same version stop the
container. Migrations run in the order they are declared, unless they name the versions they come `before` or
`after`, never a number. A package registers its own by tagging the service `phil_settings.migration` with
`version`, `before` and `after`.

## The steps

| Step | Does |
|---|---|
| `Step::rename($from, $to)` | Moves each value of `$from` to `$to`, unless the subject already has a value of `$to`. A value the new setting refuses, or equal to its default, is dropped. A value of another scope than the new setting's is dropped. |
| `Step::convert($key, $converter)` | Gives each stored value, decoded from JSON, to the function, and stores what it returns once the setting has checked it: a type changed, a choice renamed, a range moved. A value refused, or equal to the default, is dropped. |
| `Step::defaultChanged($key, $previous, $keepForSite = true)` | Drops the values equal to the new default, which no longer differ from it. A site's setting without a stored value gets the previous default, so that the site behaves as before; `keepForSite: false` lets the new default apply. A user who never chose gets the new default. |
| `Step::remove($key)` | Drops every value of a key no longer declared. |

The steps of a migration run in their order, each seeing what those before it changed. Every step can run twice and
change nothing the second time. A step naming a setting that is not declared, or a previous default its setting
refuses, stops the migration before anything is written.

## Running the migrations

```sh
php bin/console phil:settings:migrate --dry-run   # what each migration would write and remove
php bin/console phil:settings:migrate             # applies the migrations not yet applied
```

Run the command at each deployment, after the database migrations that create the bundle's tables. Each migration is
written in one transaction with the record of its version, in `phil_settings_migrations`: run again, the command says
that every migration is applied and changes nothing; of two runs at once, the second fails on the version and changes
nothing.

`Application\Migration\SettingMigrator` does the same in code: `pending()` lists the versions not yet applied, and
`migrate(dryRun: true)` returns a `MigrationReport` per migration without writing.

## What a migration does not do

A migration changes how values are stored, not who sees what: it dispatches no `SettingsChanged` event. The values of
the keys it touches are held in memory while it runs, every subject's at once. It reads values across subjects with
the `SettingMigrationStore` port, which the DBAL and memory stores implement.

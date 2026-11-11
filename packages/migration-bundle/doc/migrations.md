# Migrations

## A migration that announces

```php
use Phil\Migration\AnnouncingMigration;
use Phil\Migration\News;

final class Version20261104120000 extends AnnouncingMigration
{
    public function up(Schema $schema): void
    {
        $this->note(['fr' => '* Nouveau calendrier.', 'en' => '* A new calendar.']);
        $this->news(['fr' => new News('Le calendrier', ['Tous les événements, au même endroit.'], 'https://example.com/calendrier')]);

        $this->addSql('...');
    }
}
```

The notes and news of every migration of a run are sent together once the run is over: nothing on `--dry-run`,
nothing when a run that was all or nothing fails, and only the migrations that stay when a run that was not fails.
A skipped migration announces nothing.

A migration that announces from a helper of its own, with a translator or the router, does so as the factory of the
application gives them; the bundle only adds the announcements.

## A base class of one's own

```php
use Doctrine\Migrations\AbstractMigration;
use Phil\Migration\Announces;
use Phil\Migration\Announcing;

abstract class SiteMigration extends AbstractMigration implements Announcing
{
    use Announces;
}
```

## A factory of one's own

A factory the application gives DoctrineMigrationsBundle stays the one that makes the migrations; the bundle
decorates it:

```yaml
doctrine_migrations:
    services:
        'Doctrine\Migrations\Version\MigrationFactory': 'App\Doctrine\Migrations\MigrationFactory'
```

The same goes for a `Doctrine\Migrations\Migrator` given there.

## One process, one run

Doctrine Migrations freezes a migration once it ran, dry run included. Each `doctrine:migrations:migrate` runs in
its own process, as the console does; a test that runs the migrations twice boots its kernel again in between.

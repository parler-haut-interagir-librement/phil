<!-- phil-dev source-sha256=a22c82ab2d0dca66c11f126efa0f11b5d6026380c2e995c8ee861fc57e4facdb -->
# Migrations

## Une migration qui annonce

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

Les notes et les nouvelles de chaque migration d'une exécution sont envoyées ensemble une fois l'exécution terminée :
rien avec `--dry-run`, rien quand échoue une exécution en tout ou rien, et seulement les migrations qui restent quand
échoue une exécution qui ne l'était pas. Une migration sautée n'annonce rien.

Une migration qui annonce depuis un outil qui lui est propre, avec un traducteur ou le routeur, le fait comme la
fabrique de l'application les lui donne ; le bundle ne fait qu'ajouter les annonces.

## Votre propre classe de base

```php
use Doctrine\Migrations\AbstractMigration;
use Phil\Migration\Announces;
use Phil\Migration\Announcing;

abstract class SiteMigration extends AbstractMigration implements Announcing
{
    use Announces;
}
```

## Votre propre fabrique

Une fabrique que l'application donne à DoctrineMigrationsBundle reste celle qui fabrique les migrations ; le bundle la
décore :

```yaml
doctrine_migrations:
    services:
        'Doctrine\Migrations\Version\MigrationFactory': 'App\Doctrine\Migrations\MigrationFactory'
```

Il en va de même pour un `Doctrine\Migrations\Migrator` donné à cet endroit.

## Un processus, une exécution

Doctrine Migrations fige une migration une fois qu'elle a été exécutée, exécution à blanc comprise. Chaque
`doctrine:migrations:migrate` s'exécute dans son propre processus, comme le fait la console ; un test qui exécute les
migrations deux fois redémarre son noyau entre les deux.

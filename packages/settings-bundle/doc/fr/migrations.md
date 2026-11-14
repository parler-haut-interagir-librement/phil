<!-- phil-dev source-sha256=f392aae01b2c7e190b5cb3dc94c2588791ac8bfb319a51d4c58a5eda85e616f9 -->
# Migrations

La déclaration d'un réglage évolue : sa clé est renommée, son type change, un choix est renommé, sa valeur par défaut
bouge. Les valeurs déjà conservées la suivent par une migration, un changement versionné appliqué une fois par une
commande, avec l'enregistrement de sa version.

## Déclarer une migration

Une migration est une classe qui implémente `Domain\Contract\SettingMigration`, déclarée avec `#[AsSettingMigration]`
sur un service configuré automatiquement :

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

La version est faite de lettres, de chiffres, de `.`, de `_` et de `-`, au plus 190 caractères ; deux migrations de la
même version arrêtent le conteneur. Les migrations s'exécutent dans l'ordre où elles sont déclarées, à moins qu'elles
nomment les versions qu'elles précèdent (`before`) ou suivent (`after`), jamais un nombre. Un paquet enregistre les
siennes en étiquetant le service `phil_settings.migration` avec `version`, `before` et `after`.

## Les étapes

| Étape | Fait |
|---|---|
| `Step::rename($from, $to)` | Déplace chaque valeur de `$from` vers `$to`, sauf si le sujet a déjà une valeur de `$to`. Une valeur que le nouveau réglage refuse, ou égale à sa valeur par défaut, est abandonnée. Une valeur d'une autre portée que celle du nouveau réglage est abandonnée. |
| `Step::convert($key, $converter)` | Donne à la fonction chaque valeur conservée, décodée du JSON, et conserve ce qu'elle retourne une fois que le réglage l'a vérifié : un type changé, un choix renommé, une plage déplacée. Une valeur refusée, ou égale à la valeur par défaut, est abandonnée. |
| `Step::defaultChanged($key, $previous, $keepForSite = true)` | Abandonne les valeurs égales à la nouvelle valeur par défaut, qui ne diffèrent plus d'elle. Un réglage du site sans valeur conservée reçoit l'ancienne valeur par défaut, pour que le site se comporte comme avant ; `keepForSite: false` laisse s'appliquer la nouvelle valeur par défaut. Un utilisateur qui n'a jamais choisi reçoit la nouvelle valeur par défaut. |
| `Step::remove($key)` | Abandonne chaque valeur d'une clé qui n'est plus déclarée. |

Les étapes d'une migration s'exécutent dans leur ordre, chacune voyant ce que les précédentes ont changé. Chaque étape
peut s'exécuter deux fois sans rien changer la seconde fois. Une étape qui nomme un réglage non déclaré, ou une ancienne
valeur par défaut que son réglage refuse, arrête la migration avant que rien soit écrit.

## Exécuter les migrations

```sh
php bin/console phil:settings:migrate --dry-run   # what each migration would write and remove
php bin/console phil:settings:migrate             # applies the migrations not yet applied
```

Exécutez la commande à chaque déploiement, après les migrations de la base de données qui créent les tables du bundle.
Chaque migration est écrite dans une seule transaction avec l'enregistrement de sa version, dans
`phil_settings_migrations` : exécutée de nouveau, la commande dit que chaque migration est appliquée et ne change rien ;
de deux exécutions en même temps, la seconde échoue sur la version et ne change rien.

`Application\Migration\SettingMigrator` fait la même chose dans le code : `pending()` liste les versions pas encore
appliquées, et `migrate(dryRun: true)` retourne un `MigrationReport` par migration sans rien écrire.

## Ce qu'une migration ne fait pas

Une migration change la façon dont les valeurs sont conservées, non qui voit quoi : elle ne répartit aucun événement
`SettingsChanged`. Les valeurs des clés qu'elle touche sont gardées en mémoire pendant son exécution, celles de chaque
sujet à la fois. Elle lit les valeurs d'un sujet à l'autre par le port `SettingMigrationStore`, qu'implémentent les
stockages DBAL et en mémoire.

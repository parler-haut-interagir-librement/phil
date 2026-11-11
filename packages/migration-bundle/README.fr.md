<!-- phil-dev source-sha256=67b75b3ea9362ebee86001452cf03b2740e6062a95b0f00be156cf0f7960c385 -->
# phil/migration-bundle

**Chaque exécution de `doctrine:migrations:migrate` annoncée une seule fois sur vos canaux de clavardage.**

phil/migration-bundle intègre `phil/migration` à une application Symfony. Les migrations de DoctrineMigrationsBundle
annoncent ce qu'elles apportent, et chaque exécution de `doctrine:migrations:migrate` est annoncée une seule fois, quel
que soit le nombre de migrations exécutées. L'annonce va aux canaux de clavardage de la configuration par
`symfony/notifier`.

Il s'adresse à une application Symfony qui déploie avec DoctrineMigrationsBundle et qui veut annoncer une version sur
Discord ou un autre transport de clavardage que prend en charge le notifier. Chaque canal nomme son transport, sa
locale, s'il porte les notes de version ou les nouvelles, ses titres et le message le plus long qu'il accepte. Hors de
Symfony, `phil/migration` se branche à la main dans Doctrine Migrations.

Une migration annonce en étendant `AnnouncingMigration`, ou une classe de base propre à l'application qui utilise le
trait `Announces`. Le bundle décore la fabrique de migrations et le migrateur que fournit DoctrineMigrationsBundle, et
envoie par les transports de chatter de `framework.notifier`.

## État

| Partie | État |
|---|---|
| Les canaux tirés de la configuration, envoyés par le chatter | Implémenté et testé |
| La fabrique de migrations et le migrateur enveloppés, la fabrique propre à l'application décorée | Implémenté et testé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'enregistrement, les transports de chatter. |
| [Configuration](doc/fr/configuration.md) | Les canaux et leurs options. |
| [Migrations](doc/fr/migrations.md) | Écrire une migration qui annonce, votre propre classe de base, votre propre fabrique. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application implémente, configure, remplace ou utilise. |
| [Tests](doc/fr/testing.md) | Comment le bundle est testé. |
| [Référence](doc/fr/reference.md) | Chaque type et chaque service du bundle. |

## Démarrage rapide

```yaml
# config/packages/phil_migration.yaml
phil_migration:
    channels:
        discord_releases:
            locale: fr
            title: '# Une nouvelle version est en ligne{counter}'
            continued: '## Suite{counter}'
        discord_news:
            locale: fr
            content: news
            heading: '# {title}'
            link: 'Pour voir la nouvelle : {url}'
```

```php
final class Version20261104120000 extends AnnouncingMigration
{
    public function up(Schema $schema): void
    {
        $this->note(['fr' => '* Nouveau calendrier.', 'en' => '* A new calendar.']);
        // ...
    }
}
```

## Prérequis

PHP 8.5 ou plus récent, Symfony 8.2 ou plus récent, DoctrineMigrationsBundle 3.7 ou plus récent, `phil/migration`, et un
transport de chatter de `symfony/notifier` pour chaque canal.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la [politique de
sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).

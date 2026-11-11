<!-- phil-dev source-sha256=5e9380a09f62cc4be8128f9271e5051ed33ed43ec0f0446f0049acba242150e9 -->
# phil/migration

**Des notes de version et des nouvelles que les migrations Doctrine envoient au clavardage une fois l'exécution
terminée.**

phil/migration permet à une migration Doctrine d'annoncer ce qu'elle apporte. Chaque migration laisse des notes de
version et des nouvelles, dans ses locales, pendant son exécution. Une fois l'exécution terminée, les notes et les
nouvelles des migrations qui sont allées jusqu'au bout sont envoyées ensemble sur des canaux de clavardage : une annonce
par exécution, quel que soit le nombre de migrations qu'elle contient. Rien n'est envoyé lors d'une exécution à blanc,
et les messages sont coupés pour respecter la longueur maximale que prend chaque transport.

Il s'adresse à une équipe qui déploie avec Doctrine Migrations et qui veut informer ses utilisateurs ou ses propres
canaux de ce qu'une version a changé. Les notes vivent dans la migration qui apporte le changement, de sorte qu'elles
sont livrées et exécutées avec elle. Chaque canal a une locale et lit soit les notes, soit les nouvelles. Dans une
application Symfony, `phil/migration-bundle` branche tout cela à partir de la configuration.

C'est une simple bibliothèque PHP sur Doctrine Migrations, `symfony/notifier` et `psr/log`. Deux décorateurs enveloppent
la fabrique de migrations et le migrateur du `DependencyFactory` de Doctrine Migrations, de sorte que la bibliothèque
voit chaque exécution sans écouter aucun événement. L'envoi passe par le port `Publisher`, avec `ChatterPublisher` sur
le chatter de Symfony comme adaptateur.

## État

| Partie | État |
|---|---|
| `AnnouncingMigration`, `Announcing`, `Announces`, `Announcements` | Implémenté et testé |
| `AnnouncingMigrationFactory`, `AnnouncingMigrator` | Implémenté et testé |
| `Channel`, `Content`, messages coupés à une longueur, numérotés | Implémenté et testé |
| `Announcer`, `Publisher`, `ChatterPublisher` | Implémenté et testé |

## Documentation

| Document | Contenu |
|---|---|
| [Référence](doc/fr/reference.md) | Les types, ce qu'annonce une exécution et quand, comment les messages sont coupés. |
| [Intégration](doc/fr/integration.md) | Le branchement des décorateurs dans Doctrine Migrations sans Symfony. |
| [Points d'extension](doc/fr/extension-points.md) | Les ports, les arguments et les données qu'une application choisit ou remplace. |
| [Tests](doc/fr/testing.md) | Comment la bibliothèque est testée. |

## Démarrage rapide

```php
final class Version20261104120000 extends AnnouncingMigration
{
    public function up(Schema $schema): void
    {
        $this->note(['fr' => '* Nouveau calendrier.', 'en' => '* A new calendar.']);
        $this->news(['fr' => new News('Le calendrier', ['Tous les événements, au même endroit.'], 'https://example.com/calendrier')]);
        // ...
    }
}
```

```php
$channels = [
    new Channel('discord_releases', 'fr', title: '# A new release is online{counter}', continued: '## Continued{counter}'),
    new Channel('discord_news', 'fr', Content::News, heading: '# {title}', link: 'Read more: {url}'),
];
$announcer = new Announcer($channels, new ChatterPublisher($chatter), $logger);
```

Une exécution de deux migrations envoie un seul message sur `discord_releases` :

```
# A new release is online

* Nouveau calendrier.
* Mise à jour des librairies.
```

Dans une application Symfony, `phil/migration-bundle` branche tout à partir de la configuration.

## Prérequis

PHP 8.5 ou plus récent avec mbstring, `doctrine/migrations` 3.9 ou plus récent, `psr/log` et `symfony/notifier`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la [politique de
sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).

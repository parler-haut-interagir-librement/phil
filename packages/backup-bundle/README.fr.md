<!-- phil-dev source-sha256=e3632f5132d53b23b6474e4b0b0b14f5060eca7607f270d3ae72cfa0bf7ae222 -->
# phil/backup-bundle

**Sauvegardez la base de données et les fichiers médias d'une application Symfony à partir de sa configuration,
restaurez-les derrière une garde, et testez qu'ils se restaurent.**

phil/backup-bundle intègre `phil/backup` à une application Symfony. Il lit la connexion Doctrine DBAL pour choisir le
client — SQLite, MySQL, MariaDB ou PostgreSQL — et lui donner les identifiants de connexion, qui n'atteignent jamais une
ligne de commande. Il sauvegarde les répertoires de médias à côté de la base de données, chiffre chaque fichier avec une
clé lue dans l'environnement et fait tourner les sauvegardes après chacune d'elles. Trois commandes font le travail :
`phil:backup:create`, `phil:backup:restore` et `phil:backup:verify`.

Une restauration est une décision prise deux fois. En production, elle est refusée à moins que
`PHIL_BACKUP_ALLOW_PRODUCTION_RESTORE` ne contienne le nom de la base de données. Partout, elle exige `--confirm` et le
nom de la base saisi de nouveau, demandé lorsque la console est interactive. Le contenu actuel est ensuite sauvegardé,
avec l'étiquette `before-restore`, et la restauration ne commence qu'une fois cette sauvegarde écrite ; les trois plus
récentes de ces sauvegardes sont conservées. Les fichiers médias ajoutés depuis la sauvegarde sont laissés, sauf si
`--prune` les supprime. Une clé est exigée, sauf si `allow_unencrypted: true` est défini. `phil:backup:verify` restaure
une sauvegarde dans une copie — un fichier temporaire pour SQLite, une base de travail pour un serveur — et la vérifie à
cet endroit, sans toucher à la base de données.

Il s'adresse aux développeurs et aux exploitants d'une application Symfony qui ont besoin de sauvegardes fiables et de
restaurations impossibles à lancer par accident. Il ne planifie pas les sauvegardes, ne les copie pas hors de la machine
et ne surveille pas leur exécution : le planificateur, le stockage et la surveillance de l'application s'en chargent ;
voir [commands.md](doc/fr/commands.md).

C'est un bundle Symfony au-dessus de `phil/backup`, du composant Console, de Doctrine DBAL et de Symfony Process. Il
exige les outils clients de la base de données là où s'exécutent les commandes.

## État

| Domaine | État |
|---|---|
| `PhilBackupBundle` : connection, directory, binaries, timeout, key, allow_unencrypted, media, rotation, restore, verify | Implémenté et testé |
| `DatabaseClients` : le client d'une connexion DBAL, sans se connecter | Implémenté et testé |
| `ProcessCommandRunner` : Symfony Process, aucun shell, une limite de temps facultative | Implémenté et testé |
| `phil:backup:create`, `phil:backup:restore`, `phil:backup:verify` | Implémenté et testé sur SQLite avec le vrai `sqlite3` ; MySQL, MariaDB et PostgreSQL par leurs lignes de commande |

## Documentation

- [Installation](doc/fr/installation.md) — prérequis, installation avec Composer, enregistrement du bundle, outils
  clients, une première clé.
- [Configuration](doc/fr/configuration.md) — chaque clé de `phil_backup`, sa valeur par défaut et son effet, les
  services qu'elle branche et chaque erreur à la construction.
- [Commandes](doc/fr/commands.md) — créer, restaurer et tester une sauvegarde, leurs options et leurs messages, la
  planification, et la copie d'une base de production pour travailler.
- [Protection des restaurations](doc/fr/restore-protection.md) — la production, la confirmation, le nom saisi de nouveau
  et la sauvegarde faite d'abord.
- [Points d'extension](doc/fr/extension-points.md) — les services sur lesquels compter, un autre exécuteur ou client.
- [Tests](doc/fr/testing.md) — comment le bundle est testé, et comment tester votre configuration.
- [Référence de l'API](doc/fr/reference.md) — chaque type du bundle, avec son rôle.

## Démarrage rapide

```bash
composer require phil/backup-bundle:^0.1@dev
```

```yaml
# config/packages/phil_backup.yaml
phil_backup:
    key: '%env(PHIL_BACKUP_KEY)%'
    media: { uploads: '%kernel.project_dir%/public/uploads' }
    rotation: { last: 3, daily: 7, weekly: 4, monthly: 12 }
```

```bash
bin/console phil:backup:create
bin/console phil:backup:verify
bin/console phil:backup:restore --confirm            # asks for the database's name
```

## Prérequis

PHP 8.5 ou plus récent, `phil/backup`, `doctrine/dbal` 4.5, et `symfony/config`, `symfony/console`,
`symfony/dependency-injection` et `symfony/process` 8.2.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la [politique de
sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).

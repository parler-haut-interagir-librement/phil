<!-- phil-dev source-sha256=050dec441799257d08a83f6592df38a47395c9139b720b5ca849f620c9831d04 -->
# phil/status-page-bundle

**La page d'état d'un service — composants, incidents, maintenances planifiées et historique — servie par
l'application, et un instantané statique de cette page publié hors de l'infrastructure surveillée.**

phil/status-page-bundle sert une page d'état à `/status`, son JSON à `/status.json` et son flux Atom à `/status.atom`.
La page montre chaque **composant** dans son état — opérationnel, en maintenance, dégradé, partiellement en panne, en
panne —, les **incidents** en cours avec leur fil de mises à jour, les **maintenances** en cours et à venir, et les
incidents résolus dans l'**historique**. L'état de chaque composant vient des vérifications et des tâches planifiées de
`phil/monitoring-bundle` quand il est installé, sinon de la configuration de l'application ; les incidents et les
maintenances viennent de la configuration, d'un fichier JSON que les opérateurs modifient, ou d'un magasin propre à
l'application.

Une page servie par le site qu'elle décrit disparaît quand le site est en panne : le bundle écrit donc un
**instantané** — `index.html`, `status.json` et `feed.atom` — et le publie vers des cibles hors de l'infrastructure
surveillée, par un port avec des adaptateurs nommés : un répertoire, un stockage Flysystem, ou un éditeur propre à
l'application. La commande `phil:status-page:push` le publie, tout comme le Scheduler à la fréquence que donne la
configuration.

La page fonctionne sans aucun script, ne pose aucun témoin, ne lit aucune session et ne charge rien d'un autre site.
Ses parties sont des recettes du kit PHIL UX — Card, Badge —, installées dans l'application par le Symfony UX Toolkit
et utilisées telles quelles.

C'est un bundle Symfony ; `phil/monitoring-bundle`, Flysystem, la console, Messenger et le Scheduler sont facultatifs.

## État

| Partie | État |
|---|---|
| Composants, incidents avec leurs mises à jour, maintenances planifiées, historique | Implémenté et testé |
| États tirés de la configuration, ou des vérifications et des tâches de `phil/monitoring-bundle`, détecté | Implémenté et testé |
| Incidents et maintenances tirés de la configuration, d'un fichier JSON ou d'un service de l'application | Implémenté et testé |
| La page en HTML, en JSON et en Atom, sans script, témoin ni session | Implémenté et testé |
| Gabarits dessinés avec les recettes du kit PHIL UX | Implémenté et testé |
| Instantané publié vers un répertoire, un stockage Flysystem ou un éditeur de l'application | Implémenté et testé |
| `phil:status-page:push`, message Messenger et horaire du Scheduler | Implémenté et testé |
| Cible de fuzzing du lecteur d'enregistrements | Implémenté et testé |
| Recette Flex | Implémenté |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation, les recettes du kit. |
| [Configuration](doc/fr/configuration.md) | Chaque option, avec un exemple. |
| [La page](doc/fr/page.md) | Ce que montre la page, comment un état se calcule, le JSON et le flux. |
| [Incidents et maintenances](doc/fr/records.md) | Le format des enregistrements, où ils sont conservés. |
| [Instantané](doc/fr/snapshot.md) | Les cibles, la commande, Messenger et le Scheduler. |
| [Points d'extension](doc/fr/extension-points.md) | Les services à remplacer, et quand c'est stable. |
| [Tests](doc/fr/testing.md) | Les tests du bundle et sa cible de fuzzing. |
| [Référence](doc/fr/reference.md) | Chaque classe et chaque service du bundle. |

## Démarrage rapide

```sh
composer require phil/status-page-bundle
php bin/console ux:install card --kit=github.com/parler-haut-interagir-librement/ux-kit
php bin/console ux:install badge --kit=github.com/parler-haut-interagir-librement/ux-kit
```

```yaml
# config/packages/phil_status_page.yaml
phil_status_page:
    title: Example status
    components:
        website: { name: Website, checks: [database] }
        api: { name: API, group: Platform, checks: [database, cache] }
    records:
        file: '%kernel.project_dir%/var/status/records.json'
    snapshot:
        base_url: 'https://status.example.org/'
        schedule: '5 minutes'
        targets:
            mirror: { type: flysystem, storage: status.storage }
```

```yaml
# config/routes/phil_status_page.yaml
phil_status_page:
    resource: '@PhilStatusPageBundle/config/routes.php'
```

## Prérequis

PHP 8.5 ou une version plus récente avec `ext-dom` et `ext-mbstring`, Symfony 8.2 ou une version plus récente, Twig
3.21 et Symfony UX Twig Component 3.7 ; `phil/url`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).

<!-- phil-dev source-sha256=b9e0e1fe5c9edaa4855b2f60e5af63513bbf90fd7fce4e42b5644cbc9d132d1c -->
# phil/sites-console-bundle

**Exploitez plusieurs sites Symfony depuis une seule console : versions et santé lues par des requêtes signées, alertes, configuration poussée une seule fois, mises à jour par lots qui s'arrêtent au premier échec.**

phil/sites-console-bundle est un bundle Symfony en deux moitiés. Sur chaque site, l'**agent** répond à des points de
terminaison signés : `status` — les versions lues dans `composer.lock` et la santé tirée des vérifications de
`phil/monitoring-bundle` —, `configuration` — des réglages communs écrits par un port du site —, `update` — un paquet
amené à une version par le propre pipeline du site — et `keys`, par lequel la console fait la rotation de sa propre
clé. Sur la console, la **console** tient un registre de sites, interroge
chacun, garde des instantanés, compare les versions des paquets surveillés d'un site à l'autre, lève des alertes,
pousse des réglages vers plusieurs sites à la fois et exécute des plans de mise à jour par lots qui s'arrêtent au
premier échec, avec un rapport par site et un journal de tout ce qui a été fait.

Chaque échange est signé dans les deux sens avec des clés Ed25519 : la console signe chaque requête pour un site, une
action et un moment ; le site signe sa réponse sur le nonce de la requête. La console garde la clé publique de chaque
site et aucun identifiant de connexion d'un site ; qui peut lire ou agir dans la console découle des rôles du jeton de
l'utilisateur, que le pare-feu remplit à partir du fournisseur d'identité. La clé de la console fait sa rotation sans couper
aucun site : chaque agent accepte la clé courante et la suivante pendant une rotation, qui s'arrête au premier échec et
reprend. Une poussée, une mise à jour ou un changement de clé livré deux fois n'est appliqué qu'une fois, grâce à
`phil/idempotency`. Le bundle ne déploie ni n'héberge de sites, et n'exécute jamais Composer sur un serveur.

## État

| Partie | État |
|---|---|
| Protocole signé : requêtes et réponses Ed25519, fenêtre de dates, nonces gardés contre le rejeu, site lié à chaque signature | Implémenté et testé |
| Rotation de la clé de la console : deux clés acceptées pendant une rotation, clé suivante déposée puis clé précédente retirée sur chaque site, arrêt au premier échec, reprise, journal | Implémenté et testé |
| Agent : `status` depuis `composer.lock` et `phil/monitoring-bundle`, `configuration` par `ConfigurationApplier` avec une liste de clés permises, `update` par `UpdateRunner`, chacun une fois par identifiant | Implémenté et testé |
| Console : registre de sites, interrogations et instantanés, alertes sur la joignabilité, la santé et les versions en retard, `SiteAlertRaised` | Implémenté et testé |
| Poussées de configuration et plans de mise à jour par lots, arrêt au premier échec, rapport par site, journal | Implémenté et testé |
| Écrans dessinés avec les recettes du kit PHIL UX, rôles tirés du jeton de l'utilisateur, CSRF sur chaque action | Implémenté et testé |
| Commandes, handlers Messenger, dépôts Doctrine sur SQLite, MySQL 8.4 et PostgreSQL 17, dépôts en mémoire pour les tests | Implémenté et testé |
| Revue de sécurité externe | Exigée avant une version bêta — voir [Modèle de sécurité](doc/fr/security-model.md) |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, le bundle sur la console et sur chaque site, les routes, les tables et les recettes du kit. |
| [Configuration](doc/fr/configuration.md) | Chaque option de la console et de l'agent, avec un exemple. |
| [Exploiter les sites](doc/fr/operations.md) | Enregistrer des sites, interroger, alertes, versions côte à côte, poussées, plans de mise à jour, rotation de la clé et journal. |
| [L'agent](doc/fr/agent.md) | Les points de terminaison, ce qu'ils répondent, et l'applicateur et l'exécuteur d'un site. |
| [Modèle de sécurité](doc/fr/security-model.md) | Les clés et leur rotation, signatures, rejeu, rôles, ce que la console garde et ce qu'elle ne fait jamais. |
| [Points d'extension](doc/fr/extension-points.md) | Les ports à implémenter, les événements à écouter, les gabarits à remplacer. |
| [Tests](doc/fr/testing.md) | Les tests du bundle, les serveurs que lisent ses tests d'intégration, ses cibles de fuzzing. |
| [Référence](doc/fr/reference.md) | Chaque classe, service, commande, route et table du bundle. |

## Démarrage rapide

```sh
php bin/console phil:sites-console:keys    # once for the console, once for each site
```

```yaml
# The console
phil_sites_console:
    console:
        enabled: true
        private_key: '%env(SITES_CONSOLE_PRIVATE_KEY)%'

# Each site
phil_sites_console:
    agent:
        enabled: true
        site_id: shop
        private_key: '%env(SITES_CONSOLE_AGENT_KEY)%'
        console_public_key: '%env(SITES_CONSOLE_PUBLIC_KEY)%'
```

```sh
php bin/console phil:sites-console:site:add shop https://shop.example.com/_sites-console <public key of the site>
php bin/console phil:sites-console:pull
```

## Prérequis

PHP 8.5 ou une version plus récente avec `ext-sodium` et `ext-mbstring`, et Symfony 8.2 ou une version plus récente.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).

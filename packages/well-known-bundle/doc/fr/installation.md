<!-- phil-dev source-sha256=8312d34657c5a0905d077b9ca9c53de5bdac404c405eebf691fdb3cd6663e565 -->
# Installation

## Prérequis

| Paquet | Version | Utilisé pour |
|---|---|---|
| PHP | 8.5 ou plus récent | |
| `symfony/http-kernel`, `symfony/http-foundation` | ^8.2 | Le contrôleur et les réponses |
| `symfony/routing` | ^8.2 | Le chargeur de routes, les routes de `change-password` et de NodeInfo |
| `symfony/config`, `symfony/dependency-injection` | ^8.2 | La configuration, les services, les documents d'autres bundles |
| `psr/clock` | ^1.0 | L'heure de la vérification de l'expiration du security.txt |
| `phil/monitoring-bundle` | ^0.1, facultatif | La vérification de l'expiration du security.txt |

## Enregistrer

```php
// config/bundles.php
return [
    // ...
    Phil\WellKnownBundle\PhilWellKnownBundle::class => ['all' => true],
];
```

## Routes

Les documents ont besoin des routes du bundle. Le fichier importe le chargeur de routes, qui ajoute une route par
document activé, et aucune pour les autres :

```yaml
# config/routes/phil_well_known.yaml
phil_well_known:
    resource: '@PhilWellKnownBundle/config/routes.php'
```

Le chargeur peut aussi être importé par son type :

```yaml
phil_well_known:
    resource: .
    type: phil_well_known
```

| Route | Chemin | Quand |
|---|---|---|
| `phil_well_known_security_txt` | `/.well-known/security.txt` | `security_txt` |
| `phil_well_known_change_password` | `/.well-known/change-password` | `change_password` |
| `phil_well_known_webfinger` | `/.well-known/webfinger` | `webfinger` |
| `phil_well_known_nodeinfo` | `/.well-known/nodeinfo` | `nodeinfo` |
| `phil_well_known_nodeinfo_document` | `nodeinfo.path`, `/nodeinfo/2.1` par défaut | `nodeinfo` |
| `phil_well_known_did_json` | `/.well-known/did.json` | `did` |
| `phil_well_known_did_configuration_json` | `/.well-known/did-configuration.json` | `did_configuration` |
| `phil_well_known_atproto_did` | `/.well-known/atproto-did` | `atproto` |
| `phil_well_known_apple_app_site_association` | `/.well-known/apple-app-site-association` | `apple_app_site_association` |
| `phil_well_known_assetlinks_json` | `/.well-known/assetlinks.json` | `asset_links` |
| `phil_well_known_` suivi du chemin | `/.well-known/` suivi du chemin | chaque entrée de `documents`, et chaque document étiqueté |

Chaque route accepte `GET` et `HEAD`, et est sans état : Symfony signale un écouteur qui ouvre la session sur l'une
d'elles. Une adresse que le bundle ne sert pas reste libre pour les routes propres à l'application.

Un site dont le serveur Web répond lui-même à `/.well-known/`, pour un défi ACME par exemple, laisse passer les autres
adresses vers Symfony.

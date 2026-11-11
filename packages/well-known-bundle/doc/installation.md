# Installation

## Requirements

| Package | Version | Used for |
|---|---|---|
| PHP | 8.5 or later | |
| `symfony/http-kernel`, `symfony/http-foundation` | ^8.2 | The controller and the answers |
| `symfony/routing` | ^8.2 | The route loader, the routes of `change-password` and NodeInfo |
| `symfony/config`, `symfony/dependency-injection` | ^8.2 | The configuration, the services, the documents of other bundles |
| `psr/clock` | ^1.0 | The time of the check of the expiry of the security.txt |
| `phil/monitoring-bundle` | ^0.1, optional | The check of the expiry of the security.txt |

## Registering

```php
// config/bundles.php
return [
    // ...
    Phil\WellKnownBundle\PhilWellKnownBundle::class => ['all' => true],
];
```

## Routes

The documents need the bundle's routes. The file imports the route loader, which adds one route per document turned
on, and none for the others:

```yaml
# config/routes/phil_well_known.yaml
phil_well_known:
    resource: '@PhilWellKnownBundle/config/routes.php'
```

The loader may also be imported by its type:

```yaml
phil_well_known:
    resource: .
    type: phil_well_known
```

| Route | Path | When |
|---|---|---|
| `phil_well_known_security_txt` | `/.well-known/security.txt` | `security_txt` |
| `phil_well_known_change_password` | `/.well-known/change-password` | `change_password` |
| `phil_well_known_webfinger` | `/.well-known/webfinger` | `webfinger` |
| `phil_well_known_nodeinfo` | `/.well-known/nodeinfo` | `nodeinfo` |
| `phil_well_known_nodeinfo_document` | `nodeinfo.path`, `/nodeinfo/2.1` by default | `nodeinfo` |
| `phil_well_known_did_json` | `/.well-known/did.json` | `did` |
| `phil_well_known_did_configuration_json` | `/.well-known/did-configuration.json` | `did_configuration` |
| `phil_well_known_atproto_did` | `/.well-known/atproto-did` | `atproto` |
| `phil_well_known_apple_app_site_association` | `/.well-known/apple-app-site-association` | `apple_app_site_association` |
| `phil_well_known_assetlinks_json` | `/.well-known/assetlinks.json` | `asset_links` |
| `phil_well_known_` and the path | `/.well-known/` and the path | each of `documents`, and each tagged document |

Every route takes `GET` and `HEAD`, and is stateless: a listener that opens the session on one of them is reported by
Symfony. An address the bundle does not serve stays free for the application's own routes.

A site whose web server answers `/.well-known/` itself, for an ACME challenge for instance, lets the other addresses
through to Symfony.

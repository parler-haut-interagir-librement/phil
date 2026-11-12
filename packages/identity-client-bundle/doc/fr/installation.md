<!-- phil-dev source-sha256=806e158a17c9a932ba95c9ce9878df54b2b1828762a8b4251eba909483f63823 -->
# Installation

| Prérequis | Contrainte | Pourquoi |
|---|---|---|
| PHP | `>=8.5` | L'analyseur d'URL WHATWG de `ext-uri` |
| `ext-sodium` | toute | Chiffre le résultat partagé d'un renouvellement |
| `symfony/security-bundle`, `symfony/security-http`, `symfony/security-core` | `^8.2` | L'authentificateur `oidc_login` |
| `web-token/jwt-library` | `^4.2` | Ce avec quoi `oidc_login` vérifie les jetons |
| `symfony/http-client` | `^8.2` | Les appels au fournisseur |
| `symfony/lock` | `^8.2` | Le verrou du renouvellement et la garde contre la réutilisation |
| `symfony/cache` | `^8.2` | Le magasin anti-réutilisation et les résultats de renouvellement |
| `symfony/clock` | `^8.2` | Chaque date du bundle, et l'attente d'un renouvellement |

```bash
composer require phil/identity-client-bundle:^0.1@dev
```

```php
// config/bundles.php
return [
    Phil\IdentityClientBundle\PhilIdentityClientBundle::class => ['all' => true],
];
```

## Le pare-feu

L'application déclare le pare-feu, et le bundle le remplit. Le pare-feu doit figurer dans `security.firewalls`, pour
que sa place parmi les autres reste le choix de l'application :

```yaml
security:
    firewalls:
        dev:
            pattern: ^/(_profiler|_wdt)
            security: false
        main:
            lazy: true
```

Le bloc qu'écrit le bundle passe en premier : tout ce qui est défini dans `security.yaml` sur le même pare-feu a donc le
dernier mot. Les options que ce bundle n'expose pas, comme `dpop` ou un `client_certificate`, se définissent là. Deux protections ne peuvent pas être désactivées ainsi : la construction s'arrête quand le pare-feu
désactive PKCE avec `S256` ou la vérification de la signature du jeton d'identité, et quand
`security.session_fixation_strategy` vaut `none`.

## Les routes

Le retour d'authentification, le chemin de départ et la déconnexion par canal arrière sont des routes du bundle de
sécurité :

```yaml
oidc_login:
    resource: security.route_loader
    type: service
```

Le chemin de la déconnexion par canal arrière reçoit des appels du fournisseur, pas d'un navigateur. Il doit être
accessible sans session ; une règle `access_control` qui le couvre exige `PUBLIC_ACCESS`.

## Magasins partagés

Les services de verrou et de cache doivent être partagés par tous les serveurs de l'application. Configurez
`framework.lock` avec un autre magasin que `flock`, la valeur par défaut, dès que l'application tourne sur plus d'un
hôte, et donnez à `cache.app` un adaptateur partagé. Le mode *worker* de FrankenPHP n'exige rien de plus : aucun service
du bundle ne garde d'état entre les requêtes.

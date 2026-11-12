<!-- phil-dev source-sha256=640f589465bceca6fe0089c224270021624e632965561a72a22040535a423cce -->
# phil/browser-security-bundle

**Les protections qu'une application Symfony demande au navigateur, en un seul bundle : une configuration, ajustable
page par page.**

phil/browser-security-bundle écrit l'en-tête `Content-Security-Policy` de chaque réponse à partir de politiques
déclarées dans la configuration, directive par directive, et vérifiées à la construction du conteneur : une source
écrite sans ses guillemets, un `;` égaré ou une directive inconnue fait échouer la construction, pas une page. Chaque
requête reçoit ses propres nonces, créés quand un gabarit en demande un pour la première fois : `csp_nonce('script')`
et `csp_nonce('style')` dans Twig, les balises de `importmap()` d'AssetMapper (par lesquelles Stimulus et Turbo se
chargent), et la balise meta `csp-nonce` que lit Turbo. Une politique plus stricte peut tourner à côté de celle qui est
appliquée, en `Content-Security-Policy-Report-Only`.

À côté viennent les en-têtes qu'un site veut sur chaque réponse : `Permissions-Policy`, les trois en-têtes
`Cross-Origin-*-Policy` et les formes report-only de deux d'entre eux, `Referrer-Policy`, `X-Frame-Options` par chemin,
`nosniff`, `X-Permitted-Cross-Domain-Policies`, `Origin-Agent-Cluster` et `Integrity-Policy`, offert désactivé. Le
bundle redirige aussi les requêtes en clair vers HTTPS et envoie HSTS, signe les cookies qu'on lui nomme, refuse une
redirection vers un hôte non permis, et fait effacer par l'écouteur de Symfony les données du site à la déconnexion, sur
chaque pare-feu. Un fichier téléversé par quelqu'un est servi avec une politique à lui, appliquée par route, par
contrôleur ou par réponse : une politique en bac à sable qui ne charge rien, `nosniff`, et une pièce jointe sauf si son
type ne peut pas s'exécuter.

Chacune de ces protections est globale dans la configuration et ajustable pour une page : une valeur par défaut de
route, un attribut de contrôleur ou un service l'ajoute, la remplace ou la retire pour ses propres réponses. Le bundle
bâtit sur ce que Symfony fait déjà et le dit, sujet par sujet.

Les rapports reviennent à un point d'entrée qui lit les deux formats (`application/csp-report` et
`application/reports+json` de la Reporting API), borné en taille et en débit, dédoublonné, sans jamais garder l'adresse
du client en clair. Chaque nouvelle violation répartit un événement, une table DBAL peut les garder avec leur compte, et
le profileur montre la politique de chaque réponse.

## État

| Partie | État |
|---|---|
| Politiques par directive, vérifiées à la construction, politiques nommées et héritage | Implémenté et testé |
| Surcharges par valeur par défaut de route, attribut de contrôleur et service | Implémenté et testé |
| Nonces pour Twig, `importmap()` et Turbo, `'strict-dynamic'`, empreintes, Trusted Types | Implémenté et testé |
| Politiques appliquée et report-only côte à côte | Implémenté et testé |
| `Cache-Control: private` sur une réponse dont la politique liste un nonce | Implémenté et testé |
| Le point d'entrée des rapports compté par un limiteur nommé de phil/rate-limit-bundle | Implémenté et testé |
| Point d'entrée des rapports, les deux formats, les rapports des autres politiques, bornes, limitation de débit, dédoublonnage, adresse anonymisée, événement | Implémenté et testé, fuzzé |
| Magasin DBAL des violations et sa commande de purge | Implémenté et testé |
| En-têtes de sécurité voisins, par chemin et par page, panneau du profileur | Implémenté et testé |
| Surcharges de chaque protection par valeur par défaut de route, attribut de contrôleur et service | Implémenté et testé |
| Redirection HTTPS et HSTS, cookies signés, redirections externes, `Clear-Site-Data` à la déconnexion | Implémenté et testé |
| La politique du contenu d'usager : en-têtes stricts pour les fichiers téléversés, par route, attribut ou réponse | Implémenté et testé |
| La construction des en-têtes d'une réponse | Mesurée par banc d'essai |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Prérequis, le bundle, la route des rapports, la table. |
| [Configuration](doc/fr/configuration.md) | Chaque option de `phil_browser_security`. |
| [Politiques](doc/fr/policies.md) | Les directives et leurs valeurs, la politique par défaut, les politiques nommées, l'héritage, les empreintes, Trusted Types. |
| [Surcharges](doc/fr/overrides.md) | Ajouter, remplacer et retirer, pour la politique et chaque autre protection : attributs, valeurs par défaut de route, services. |
| [Nonces](doc/fr/nonces.md) | `csp_nonce()`, `csp_meta()`, `importmap()`, Turbo, Stimulus, `'strict-dynamic'`, la mise en cache. |
| [Déploiement](doc/fr/deployment.md) | D'abord en report-only, puis appliquée, avec les deux en-têtes à la fois. |
| [Rapports](doc/fr/reports.md) | Le point d'entrée, les deux formats, les bornes, la limitation de débit, le dédoublonnage, la vie privée, l'événement, le magasin. |
| [En-têtes voisins](doc/fr/headers.md) | Chaque en-tête à côté de la politique, les cadres par chemin, l'intégrité, `Clear-Site-Data` à la déconnexion. |
| [HTTPS](doc/fr/https.md) | La redirection, HSTS, un mandataire, et ce que Symfony fait déjà. |
| [Cookies signés](doc/fr/cookies.md) | La signature, la rotation du secret, une page. |
| [Redirections externes](doc/fr/redirects.md) | Les hôtes permis, les trois actions, une page. |
| [Contenu d'usager](doc/fr/user-content.md) | Les en-têtes stricts d'un fichier téléversé, par route, par contrôleur ou par réponse. |
| [Mode worker et profileur](doc/fr/runtime.md) | Ce qui vit où pendant une requête, `kernel.reset`, le panneau du profileur. |
| [Points d'extension](doc/fr/extension-points.md) | Les ports, services et événements qu'une application utilise ou remplace. |
| [Tests](doc/fr/testing.md) | Les tests, le seuil de mutation, la cible de fuzzing et le banc d'essai. |
| [Référence](doc/fr/reference.md) | Chaque type du bundle, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/browser-security-bundle
```

```yaml
# config/packages/phil_browser_security.yaml
phil_browser_security:
    policies:
        default:
            directives:
                default-src: ["'self'"]
                img-src: ["'self'", 'data:']
                object-src: ["'none'"]
                base-uri: ["'self'"]
                frame-ancestors: ["'self'"]
            strict_dynamic: true
    reporting:
        storage: true
```

```yaml
# config/routes/phil_browser_security.yaml
phil_browser_security:
    resource: '@PhilBrowserSecurityBundle/config/routes.php'
```

```twig
<head>
    {{ csp_meta() }}
    {{ importmap('app') }}
    <style nonce="{{ csp_nonce('style') }}">body { margin: 0 }</style>
</head>
```

## Prérequis

PHP 8.5 ou plus récent avec `ctype`, `mbstring`, `sodium` et `uri`, Symfony 8.2 ou plus récent, et `phil/rate-limit`.
Twig, AssetMapper, le rate limiter, un pool PSR-6, Doctrine DBAL, la console, security-http et `phil/ip-anonymizer` sont
facultatifs, chacun activant une partie.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).

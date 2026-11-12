<!-- phil-dev source-sha256=4ba84bdc59afd2063b1df9cea87e449c9748c63928c104b57a5deb05dde13123 -->
# Installation

## Prérequis

| Prérequis | Version | Pourquoi |
|---|---|---|
| PHP | 8.5 ou plus récent | Classes `readonly`, constantes typées, `clone` avec propriétés, `array_any()`. |
| `ext-ctype`, `ext-mbstring` | toute | Les nombres d'un rapport ; des champs coupés à un nombre de caractères. |
| `ext-sodium` | toute | Nonces et empreintes en base64, encodés en temps constant. |
| `ext-uri` | toute | La cible d'une redirection, résolue comme un navigateur la résout. |
| `psr/clock`, `psr/event-dispatcher` | `^1.0` | La date d'un rapport ; l'événement `ViolationReported`. |
| `symfony/config`, `dependency-injection`, `http-foundation`, `http-kernel`, `routing` | `^8.2` | La configuration, le câblage, l'écouteur, le point d'entrée et sa route. |
| `phil/rate-limit` | `^0.1` | La garde qui refuse la limitation de débit sans son composant, ou sur un pool propre à chaque serveur en `prod`. |

Paquets facultatifs, chacun activant une partie :

| Paquet | Active |
|---|---|
| `twig/twig` | `csp_nonce()` et `csp_meta()`. |
| `symfony/asset-mapper`, `symfony/twig-bridge` | Le nonce sur les balises de `importmap()`. |
| `symfony/rate-limiter` | La limitation de débit du point d'entrée des rapports ; la construction échoue sans lui, sauf si `reporting.rate_limit` vaut `false`. |
| `phil/rate-limit-bundle` | Les corps comptés par un limiteur nommé de ce bundle. |
| `symfony/cache` (ou tout pool PSR-6) | Le stockage de la limitation de débit et la fenêtre de dédoublonnage. |
| `symfony/clock` | Le service `clock` qui date les rapports. |
| `phil/ip-anonymizer` | L'adresse du client gardée tronquée avec chaque violation. |
| `doctrine/dbal`, `doctrine/doctrine-bundle` | La table des violations. |
| `doctrine/orm` | La table ajoutée au schéma que génère l'ORM. |
| `symfony/console` | `phil:csp:purge-reports`. |
| `symfony/security-http` | `Clear-Site-Data` à la déconnexion. |
| `psr/log` | L'avertissement d'une redirection externe. |
| `symfony/web-profiler-bundle` | Le panneau du profileur. |

## Installer avec Composer

```sh
composer require phil/browser-security-bundle
```

## Enregistrer le bundle

```php
// config/bundles.php
return [
    Symfony\Bundle\FrameworkBundle\FrameworkBundle::class => ['all' => true],
    Symfony\Bundle\TwigBundle\TwigBundle::class => ['all' => true],
    Phil\BrowserSecurityBundle\PhilBrowserSecurityBundle::class => ['all' => true],
];
```

Sans aucune configuration, chaque réponse reçoit la [politique par défaut](policies.md#la-politique-par-défaut),
appliquée, les [en-têtes voisins](headers.md) qui ne brisent rien, un refus des
[redirections externes](redirects.md) et, quand `kernel.debug` est désactivé, [HTTPS et HSTS](https.md). Une
application derrière un mandataire le déclare d'abord, sinon chaque requête semble en clair : voir
[le mandataire](https.md#derrière-un-mandataire).

## Importer la route des rapports

```yaml
# config/routes/phil_browser_security.yaml
phil_browser_security:
    resource: '@PhilBrowserSecurityBundle/config/routes.php'
```

La route `phil_csp_report` répond à `POST /_csp/report`, sans session. Un préfixe la déplace ; les politiques suivent,
puisque le bundle génère l'adresse à partir de la route. Sans la route, et sans `reporting.uri`, les politiques ne
portent aucune directive de rapport.

Le point d'entrée doit rester joignable sans authentification : listez son chemin dans une règle `access_control` avec
`PUBLIC_ACCESS`, ou hors de tout pare-feu qui exige un utilisateur.

## Créer la table

Avec `reporting.storage` activé et l'ORM installé, la table `phil_csp_report` rejoint le schéma que génère l'ORM :

```sh
bin/console doctrine:migrations:diff
bin/console doctrine:migrations:migrate
```

Sans l'ORM, une migration la crée à partir de `ReportTable::configure()` :

```php
use Phil\BrowserSecurityBundle\Infrastructure\Doctrine\ReportTable;

foreach (ReportTable::configure(new Schema())->toSql($connection->getDatabasePlatform()) as $sql) {
    $this->addSql($sql);
}
```

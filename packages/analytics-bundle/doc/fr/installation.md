<!-- phil-dev source-sha256=607416eeb4e86e7893c2d4e67d3f9869b90e5b45ac3c1d57d4039771e063a4f6 -->
# Installation

## Prérequis

| Prérequis | Contrainte | Pourquoi |
|---|---|---|
| PHP | `>=8.5` | Le code utilise la syntaxe et les fonctions de PHP 8.5 (`array_any()`, `array_all()`, `new Foo()->bar()` sans parenthèses, constantes de classe typées). |
| `phil/ddd-bundle` | `^0.1@dev` | Chaque objet-valeur refuse une valeur invalide en lançant `Phil\DddBundle\Domain\Exception\InvalidValue` (une `InvalidArgumentException`). |
| `phil/bot-detection-bundle` | `^0.1@dev` | `AdmissionRule` décide d'après le `Verdict` du navigateur. |
| `phil/ip-anonymizer` | `^0.1@dev` | `ClientDigester` compose son `Pipeline`, `KeyedHash` et `Truncation`, sous l'un de ses fournisseurs de sel. |
| `phil/rate-limit` | `^0.1@dev` | `RateLimitGuard` refuse, en `prod`, un pool du sel ou du limiteur propre à chaque serveur. |
| `symfony/dependency-injection` | `^8.2` | `PhilAnalyticsBundle` étend `AbstractBundle`. |
| `symfony/config` | `^8.2` | L'arbre de configuration de `phil_analytics`. |
| `symfony/http-foundation` | `^8.2` | Le point de collecte lit une `Request` et répond par une `Response`. |
| `symfony/rate-limiter` | `^8.2` | Le budget de collecte par défaut, `SymfonyCollectionRateLimiter`. |
| `psr/cache` | `^3.0` | Les réserves du sel quotidien et de l'état du limiteur. |
| `psr/clock` | `^1.0` | Le jour UTC d'une page vue et des tâches quotidiennes, depuis le service `clock` de l'application. |

`ext-mbstring` met un terme de recherche en minuscules, `ext-sodium` fabrique les jetons des liens de partage, et
`ext-uri` lit une origine comme le fait un navigateur. `hash()` et `inet_pton()` font partie d'une version standard de PHP.

Les adaptateurs par défaut utilisent des paquets facultatifs, suggérés par le bundle et configurés seulement quand ils
sont installés :

| Paquet | Pour |
|---|---|
| `doctrine/dbal` et DoctrineBundle | Le stockage des compteurs par défaut, `counters.store: dbal` |
| `doctrine/orm` | Les tables des compteurs dans le schéma que génère l'ORM |
| `symfony/lock` | La première réclamation du sel quotidien, atomique entre les serveurs |
| `symfony/routing` | L'import de la route du point de collecte |
| `symfony/scheduler` et `symfony/messenger` | Les tâches quotidiennes de l'horaire `phil_analytics` |
| `phil/broken-flow-alerts` et `symfony/notifier` | L'alerte quand un objectif cesse d'être atteint |

Une application Symfony avec FrameworkBundle, DoctrineBundle et l'ORM les a tous, sauf la dernière ligne.

## Installer

Le bundle est en `0.1.x-dev` (`dev-main` est un alias de `0.1.x-dev`) et n'est pas encore publié sur Packagist, pas
plus que `phil/ddd-bundle` et `phil/bot-detection-bundle`. D'ici là, déclarez un dépôt qui contient les trois paquets,
un dépôt `path` vers une copie locale ou un dépôt `vcs` par paquet :

```json
{
    "repositories": [
        { "type": "path", "url": "../phil-bundles/packages/*" }
    ]
}
```

Puis requérez le bundle ; les deux bundles voisins viennent avec lui :

```bash
composer require phil/analytics-bundle:^0.1@dev
```

Le `composer.json` du bundle déclare `"minimum-stability": "dev"` et `"prefer-stable": true`. Cela ne vaut que quand le
bundle est le paquet racine (pour lancer ses tests seul) ; une application qui le requiert a besoin de `@dev` sur la
contrainte, comme ci-dessus, ou des mêmes réglages de stabilité.

## Enregistrer le bundle

Ajoutez-le à `config/bundles.php` :

```php
return [
    // ...
    Phil\AnalyticsBundle\PhilAnalyticsBundle::class => ['all' => true],
];
```

Enregistré sans configuration, le bundle configure ses adaptateurs par défaut ([configuration.md](configuration.md)) :
les compteurs sur la connexion Doctrine `default`, le sel quotidien et l'état du limiteur dans `cache.app`, et la
rétention quotidienne. Il ne collecte rien tant qu'un site n'est pas déclaré et que la route n'est pas importée :

```yaml
# config/packages/phil_analytics.yaml
phil_analytics:
    sites:
        spring-con:
            origins: ['https://example.com']
            routes: { home: '/', talk: '/talks/{slug}' }
```

```yaml
# config/routes/phil_analytics.yaml
phil_analytics:
    resource: '@PhilAnalyticsBundle/config/routes.php'
    prefix: /analytics
```

Créez ensuite les tables des compteurs, par une migration (`bin/console doctrine:migrations:diff`) ou par
`bin/console doctrine:schema:update --force` en développement.

`phil/bot-detection-bundle` n'a pas besoin d'être enregistré pour que ce bundle fonctionne : seule son énumération
`Verdict` est utilisée.

## Et ensuite

1. Lisez [counters.md](counters.md) pour savoir ce qui est stocké et pourquoi rien d'autre ne l'est.
2. Déclarez les sites et faites pointer le script de collecte vers le point de collecte
   ([collection-and-admission.md](collection-and-admission.md)).
3. Remplacez les adaptateurs qui ne conviennent pas, comme la recherche de pays, par des services de l'application
   ([extension-points.md](extension-points.md)), et construisez les tableaux de bord comme le décrit
   [small-number-suppression.md](small-number-suppression.md).

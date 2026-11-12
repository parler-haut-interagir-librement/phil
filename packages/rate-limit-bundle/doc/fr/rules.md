<!-- phil-dev source-sha256=26ab67bf51d52a91425eda8088570c05631197cccef80d0ced47a133fe88cb58 -->
# Limiter les requêtes

Une règle nomme un limiteur et, au besoin, ce que consomme une requête et pour qui elle compte. Elle s'écrit de trois
façons.

## L'attribut

```php
use Phil\RateLimitBundle\Domain\Attribute\RateLimit;

#[RateLimit('api')]
final class ExportController
{
    #[RateLimit('exports', amount: 5, key: 'user')]
    public function export(): Response
    {
        // ...
    }
}
```

`#[RateLimit]` va sur une classe de contrôleur ou sur une action, et se répète. Un nom vide, une clé vide ou une
quantité inférieure à un est refusé à la construction de l'attribut.

## La valeur par défaut de route

```yaml
search:
    path: /search
    controller: App\Controller\SearchController
    defaults:
        _rate_limit: [api, { limiter: search, key: ip_route, amount: 2 }]
```

`_rate_limit` contient le nom d'un limiteur, un tableau associatif de `limiter`, `amount` et `key`, ou une liste d'au
plus seize d'entre eux.

## L'ordre

Les attributs de la classe viennent en premier, puis ceux de l'action, puis les règles de la route. Chacun consomme sur
son limiteur dans cet ordre ; le premier qui refuse arrête la requête, et ceux d'avant gardent ce qu'ils ont consommé.
Une règle dont la stratégie de clé ne trouve aucune clé (`user` sans utilisateur connecté) est laissée de côté.

## Ce qui est vérifié à la construction de l'application

`Infrastructure\Http\RateLimitCacheWarmer` lit chaque route quand Symfony construit le conteneur : les attributs de son
contrôleur et sa valeur par défaut `_rate_limit`. Un limiteur ou une stratégie de clé que rien ne déclare, ou une règle
d'une autre forme, arrête la construction avec le nom de la route et ce qui est déclaré. Le préchauffeur n'est pas
facultatif : `cache:warmup`, `cache:clear` et la première requête d'un conteneur neuf l'exécutent tous.

Un contrôleur écrit sous forme de fermeture n'a pas de classe à lire : ses règles sont vérifiées à sa première requête,
où la même erreur lance `UnknownLimiter`.

Seule la requête principale est comptée : une sous-requête, comme un fragment ou le contrôleur d'erreur, ne l'est pas.

## Les limiteurs d'autres paquets

Un limiteur est tout service qui implémente `Application\Contract\Limiter`, étiqueté `phil_rate_limit.limiter` avec son
`name` et, au besoin, ses `key` et `amount` par défaut. Un paquet qui compte les requêtes à sa façon, sur des quotas ou
sur un compteur de facturation, donne cette étiquette à ses limiteurs, et les règles les nomment comme ceux du bundle.

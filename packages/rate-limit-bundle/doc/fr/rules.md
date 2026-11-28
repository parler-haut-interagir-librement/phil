<!-- phil-dev source-sha256=f3c1418f312bd871e71e2d411646af805268092d9d5e9208264d329e29fd816d -->
# Limiter les requêtes

Une règle nomme un limiteur et, au besoin, ce que consomme une requête et pour qui elle compte. Elle s'écrit de trois
façons : l'attribut `#[RateLimit]` de Symfony sur un contrôleur, la valeur par défaut de route `_rate_limit`, et l'option
`rate_limit` d'un formulaire ([Formulaires](forms.md)).

## L'attribut de Symfony

```php
use Symfony\Component\HttpKernel\Attribute\RateLimit;

#[RateLimit('api')]
final class ExportController
{
    #[RateLimit('exports', key: 'user', tokens: 5)]
    public function export(): Response
    {
        // ...
    }
}
```

L'attribut est celui de Symfony, appliqué par l'écouteur de Symfony. Chaque limiteur du bundle est une fabrique étiquetée
`rate_limiter` avec son nom, à côté des limiteurs de la configuration `rate_limiter` du framework : l'attribut les nomme
donc de la même façon. Sur un limiteur du bundle :

| Option | Ce qu'elle fait |
|---|---|
| `limiter` | Le nom du limiteur. |
| `key` | Omise, ou la clé que Symfony calcule pour elle (l'adresse du client et le chemin) : la stratégie de clé du limiteur. Le nom d'une stratégie de clé (`ip`, `user`, `user_or_ip`, `ip_route` ou l'identifiant d'un service `KeyResolver`) : cette stratégie. Toute autre chose (un paramètre de route écrit `{id}`, le résultat d'une expression) : cette valeur, gardée sous forme d'empreinte à clé. |
| `tokens` | Ce que consomme une requête ; la valeur par défaut de Symfony est un, et le `amount` du limiteur ne s'applique pas. |
| `methods` | Les méthodes HTTP limitées, comme Symfony les lit. |
| `if` | Un booléen, une fermeture ou une expression : quand elle est fausse, rien n'est consommé. Ne la faites jamais dépendre de ce que choisit le client. |
| `exposeHeaders` | Les en-têtes `X-RateLimit-*` de Symfony, à côté des `RateLimit-Policy` et `RateLimit` du bundle. |

La consommation passe par le bundle : la clé est hachée, un limiteur en observation laisse passer la requête, un refus
répartit `RateLimitExceeded` et le `RateLimitExceededEvent` de Symfony et reçoit la réponse du répondeur du bundle, et la
réponse porte les en-têtes du bundle. Une stratégie qui ne trouve aucune clé (`user` sans utilisateur connecté) ne
consomme rien.

Une action peut demander ce que l'attribut a consommé, sans consommer de nouveau :

```php
use Symfony\Component\RateLimiter\RateLimit as ConsumedRateLimit;

#[RateLimit('exports', key: 'user', tokens: 5)]
public function export(?ConsumedRateLimit $limit): Response
```

L'argument accepte null quand aucun attribut ne peut s'appliquer, un `methods` ou un `if` laissant la requête de côté ;
sinon Symfony lance une `LogicException`. Il contient les limites des attributs seulement, pas celles de la valeur par
défaut de route.

L'attribut a besoin du `rate_limiter` du framework, activé par défaut : s'il est désactivé, l'écouteur de Symfony est
absent, et la construction s'arrête sur la première route dont le contrôleur porte l'attribut.

## La valeur par défaut de route

```yaml
search:
    path: /search
    controller: App\Controller\SearchController
    defaults:
        _rate_limit: [api, { limiter: search, key: ip_route, amount: 2 }]
```

`_rate_limit` contient le nom d'un limiteur, un tableau associatif de `limiter`, `amount` et `key`, ou une liste d'au
plus seize d'entre eux ; chacun est lu comme un `Domain\ValueObject\Rule`. L'écouteur lit les règles sur
`kernel.controller`, puis retire l'attribut de requête : l'écouteur d'attributs de Symfony garde ce que ses attributs ont
consommé sous ce même nom.

## L'ordre

Sur une requête, les limites sont consommées dans cet ordre, le premier refus arrêtant la requête et les limites d'avant
gardant ce qu'elles ont consommé :

| Étape | Quand | Quoi |
|---|---|---|
| 1 | `kernel.controller`, avant l'écouteur d'attributs de Symfony | La valeur par défaut de route `_rate_limit`, dans l'ordre écrit. |
| 2 | `kernel.controller`, une fois le contrôleur connu | Les `#[RateLimit]` de Symfony, ceux de la classe avant ceux de l'action, avant la résolution des arguments : une requête refusée ne charge aucune entité. |
| 3 | `kernel.controller_arguments` | `#[IsGranted]`, et un `#[RateLimit]` dont le `key` ou le `if` lit les arguments : une fermeture, ou une expression qui nomme `args`. |
| 4 | Le contrôleur | L'option `rate_limit` d'un formulaire, à la soumission. |

Une requête que `#[IsGranted]` refuse a donc déjà consommé ses limites : un attaquant sans accès dépense quand même la
limite, et un client honnête refusé par l'accès aussi. Pour ne compter que les requêtes qui passent le contrôle d'accès,
donnez à la limite un `if` qui lit les arguments, ce qui la place après `#[IsGranted]`, ou limitez l'action avec l'option
de formulaire.

## Ce qui est vérifié à la construction de l'application

`Infrastructure\Http\RateLimitCacheWarmer` lit chaque route quand Symfony construit le conteneur : sa valeur par défaut
`_rate_limit`, chaque règle nommant un limiteur et une stratégie de clé déclarés, et les attributs `#[RateLimit]` de son
contrôleur, chacun nommant un limiteur du bundle ou de la configuration du framework. Un nom que rien ne déclare, ou une
règle d'une autre forme, arrête la construction avec le nom de la route et ce qui est déclaré. Le préchauffeur n'est pas
facultatif : `cache:warmup`, `cache:clear` et la première requête d'un conteneur neuf l'exécutent tous.

Un contrôleur écrit sous forme de fermeture n'a pas de classe à lire : ses attributs sont vérifiés à sa première
requête, où Symfony lance une exception sur un limiteur inconnu.

La valeur par défaut de route est comptée sur la requête principale seulement : une sous-requête, comme un fragment ou le
contrôleur d'erreur, ne l'est pas. L'attribut de Symfony compte partout où Symfony l'applique.

## Les limiteurs d'autres paquets

Un limiteur est tout service qui implémente `Application\Contract\Limiter`, étiqueté `phil_rate_limit.limiter` avec son
`name` et, au besoin, ses `key` et `amount` par défaut. Un paquet qui compte les requêtes à sa façon, sur des quotas ou
sur un compteur de facturation, donne cette étiquette à ses limiteurs, et l'attribut, la valeur par défaut de route et les formulaires les nomment comme
ceux du bundle. Un limiteur de la configuration du framework du même nom arrête la construction : l'attribut ne pourrait
pas les distinguer.

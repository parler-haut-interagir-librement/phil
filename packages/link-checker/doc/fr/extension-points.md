<!-- phil-dev source-sha256=4121407367d476f62bdc13d558c773f7a37bb558930c5fdc09fc1664b57b27af -->
<!-- phil-dev source-sha256=x -->
# Points d'extension

La bibliothèque est dans ses versions `0.x` : les ports, les objets-valeurs et les exceptions peuvent changer dans une
version mineure, ce que dit le journal des modifications. Ils sont destinés à être stables à partir de la version 1.0.

## Ports

| Port | Implémenté par | Rôle |
|---|---|---|
| `Contract\LinkProbe` | `HttpLinkProbe`, ou une doublure de test | vérifie une adresse, et donne le HTML d'une page sur demande |
| `Contract\HostThrottle` | `IntervalThrottle`, ou un limiteur partagé | attend qu'une requête vers un hôte puisse partir |
| `Contract\InternalPages` | l'application | donne le HTML d'une page du site sans requête |
| `Contract\DeadLinkFixer` | un bundle ou l'application | répare un lien cassé ou redirigé d'un rapport |

## Les lots et les files d'attente

La bibliothèque n'envoie aucun message et n'a besoin d'aucune file d'attente. `LinkChecker::check()` prend n'importe
quel itérable de `ContentLinks` et produit un `LinkReport` par contenu : le lot est l'unité de travail, à l'intérieur de
laquelle chaque adresse n'est vérifiée qu'une fois. Un bundle ou une application découpe le travail là où cela lui
convient :

- un message de Messenger qui porte les identifiants de quelques contenus — des identifiants seulement, jamais leur
  HTML —, dont le *handler* les charge, appelle `check()` et enregistre les rapports ;
- une commande planifiée qui parcourt les contenus modifiés depuis la dernière exécution, une page d'une liste à la
  fois ;
- l'enregistrement d'un contenu, vérifié aussitôt avec `checkContent()`.

Les adresses nommées par plusieurs lots sont vérifiées une fois par lot, et une fois par durée de vie du cache d'un lot
à l'autre quand un cache est donné. Dans un lot, le vérificateur retient au plus 10 000 résultats ; au-delà, les plus
anciens sont oubliés.

## Le cache

Le vérificateur prend un `Symfony\Contracts\Cache\CacheInterface`, que tout pool de `symfony/cache` implémente. Il
garde le résultat de chaque adresse externe sous `phil_link_checker.` suivi de l'empreinte xxh128 de sa clé : l'adresse
n'apparaît jamais dans la clé de cache. Un résultat à examiner est gardé `reviewTtl` secondes (une heure par défaut),
tout autre `ttl` secondes (un jour par défaut, 30 jours au plus) ; `reviewTtl` ne peut pas dépasser `ttl`.

```php
new LinkChecker($probe, cache: $pool, ttl: 7 * 86_400, reviewTtl: 6 * 3_600);
```

Les pages du site lui-même ne sont jamais mises en cache : elles changent avec le site et sont peu coûteuses à
demander. Une entrée d'une autre forme — écrite par une autre version, par exemple — est ignorée, et l'adresse est
vérifiée de nouveau.

## Le limiteur de débit

`IntervalThrottle` garde deux requêtes vers le même hôte espacées d'au moins `interval` secondes (une par défaut, 60 au
plus) ; les requêtes vers des hôtes différents n'attendent pas les unes les autres. Il dort par une horloge de Symfony,
retient les `maxHosts` derniers hôtes (1 000 par défaut) et les oublie à `reset()`.

Il règle le rythme d'un seul processus. Une application qui vérifie des liens dans plusieurs *workers* à la fois
implémente `HostThrottle` sur un stockage partagé — le RateLimiter de Symfony avec un stockage de verrous, par
exemple :

```php
use Phil\LinkChecker\Contract\HostThrottle;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

final readonly class SharedThrottle implements HostThrottle
{
    public function __construct(private RateLimiterFactoryInterface $limiters)
    {
    }

    public function wait(string $host): void
    {
        $this->limiters->create($host)->reserve()->wait();
    }
}
```

Les redirections que phil/safe-http suit dans une même vérification ne sont pas espacées : elles font partie de la même
requête.

## La sonde

`HttpLinkProbe` prend le transport que phil/safe-http enveloppe — `HttpClient::create()` de Symfony par défaut, un
`MockHttpClient` dans les tests —, les `Limits` de chaque lecture (temps, octets, redirections, `User-Agent`), des
adresses fixes pour des noms d'hôte, toujours vérifiées contre les réseaux privés, et le limiteur de débit :

```php
new HttpLinkProbe($httpClient, new Limits(timeout: 3.0, maxRedirects: 5, userAgent: 'ExampleLinks/1.0'), throttle: $throttle);
```

Un autre `LinkProbe` peut la remplacer — un qui répond à partir d'une liste d'adresses connues, par exemple. Il reçoit
l'adresse sans son fragment, et on ne lui demande le HTML que pour une page du site qui a une ancre à trouver.

## Les pages internes

```php
use Phil\LinkChecker\Contract\InternalPages;
use Phil\Url\Url;

final readonly class RenderedPages implements InternalPages
{
    public function __construct(private PageRenderer $renderer)
    {
    }

    public function html(Url $url): ?string
    {
        return $this->renderer->renderPath($url->path, $url->query);
    }
}
```

Null veut dire que l'application ne peut pas répondre ; la page est alors demandée par HTTP. Le HTML donné est lu
jusqu'aux octets de l'extracteur.

## Le correcteur

`DeadLinkFixer` est le contrat d'une réparation en un clic : `supports()` dit si un lien d'un rapport peut être réparé,
`fix()` le répare — une redirection créée depuis une adresse interne morte vers la page qui l'a remplacée, ou le lien
réécrit vers sa cible permanente. La bibliothèque n'appelle ni l'un ni l'autre : un bundle ou l'application offre la
réparation sur ses propres écrans, phil/redirects-bundle étant l'endroit naturel pour créer la redirection.

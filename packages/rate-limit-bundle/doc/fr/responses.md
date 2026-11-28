<!-- phil-dev source-sha256=368413a73a64e8db24534b36301d78e895f14524e6e54f024063c829174f4ff1 -->
# Réponses

## Une requête acceptée

La réponse porte les en-têtes du limiteur à qui il reste le moins, qu'il vienne de la valeur par défaut de route ou du
`#[RateLimit]` de Symfony, quand l'un de ses limiteurs a une limite :

```http
RateLimit-Policy: "api";q=100;w=60
RateLimit: "api";r=42;t=17
```

`q` est la limite, `w` la fenêtre en secondes pour une fenêtre fixe ou glissante, `r` ce qui reste, et `t` les secondes
jusqu'à ce que le limiteur retrouve sa pleine capacité. Quand une politique ne donne pas un tel moment, `t` est la durée
de la fenêtre, une borne supérieure. Avec `headers: legacy`, les mêmes valeurs sont `RateLimit-Limit`,
`RateLimit-Remaining` et `RateLimit-Reset`.

Ce sont les champs du brouillon de l'IETF, que Symfony n'écrit pas. L'option `exposeHeaders` de Symfony écrit ses propres
`X-RateLimit-Limit`, `X-RateLimit-Remaining` et `X-RateLimit-Reset` à côté d'eux, et rend la réponse privée : laissez-la
désactivée sur une connexion ou une réinitialisation de mot de passe, où les en-têtes disent à un attaquant combien de
tentatives restent.

## Un refus

`TooManyRequests`, une `TooManyRequestsHttpException`, reçoit la réponse `429 Too Many Requests` avec `Retry-After`
(les secondes jusqu'à ce que le limiteur permette le coup suivant, arrondies vers le haut) et les en-têtes du limiteur
qui a refusé, que la valeur par défaut de route ou le `#[RateLimit]` de Symfony l'ait nommé.

Un refus de l'attribut de Symfony sur un limiteur de la configuration `rate_limiter` du framework, une
`TooManyRequestsHttpException` causée par une `RateLimitExceededException`, reçoit le même corps avec son seul
`Retry-After` : le bundle ne connaît pas la politique de ce limiteur, et ne nomme aucun limiteur à la page.

À une requête dont le format est JSON (`json`, `jsonld`, `jsonapi` ou `problem`) ou dont le premier type acceptable est
`application/json` ou se termine par `+json`, le corps est un problème RFC 9457 :

```json
{"type": "about:blank", "title": "Too many requests", "status": 429, "detail": "Too many requests. Try again in 12 seconds.", "retry_after": 12}
```

À une autre requête, avec Twig, le corps est `error_template`, rendu avec `locale`, `title`, `detail`, `retry_after` et
`limiter`. Sans Twig, la page d'erreur de Symfony répond, avec les mêmes en-têtes. Le titre et le détail sont traduits
dans le domaine `phil_rate_limit` ; le bundle fournit l'anglais et le français.

Redéfinissez la page dans `templates/bundles/PhilRateLimitBundle/too_many_requests.html.twig`, ou nommez un autre
gabarit.

## Les événements

Avant la réponse à un refus, `Application\Event\RateLimitExceeded` est réparti avec le nom du limiteur, la clé sous
laquelle la requête a compté (une empreinte), la décision, le nom de la route et, pour un formulaire, son nom.
Écoutez-le pour journaliser, compter ou alerter :

```php
#[AsEventListener]
final readonly class LogRefusals
{
    public function __invoke(RateLimitExceeded $event): void
    {
        $this->logger->notice('Rate limit {limiter} refused a request.', ['limiter' => $event->limiter, 'route' => $event->route]);
    }
}
```

Le `RateLimitExceededEvent` de Symfony suit, avec le nom du limiteur, la même clé hachée et un `RateLimit` de la décision,
pour qu'un écouteur de l'événement de Symfony entende aussi les refus du bundle. Sur un limiteur de la configuration du
framework, l'écouteur de Symfony le répartit lui-même, avec la clé telle que Symfony l'a calculée, avant tout hachage, et
`RateLimitExceeded` n'est pas réparti.

Un limiteur en observation ne refuse rien : là où il l'aurait fait, `Application\Event\RateLimitObserved` est réparti
avec les mêmes champs, un avertissement est journalisé, et la requête continue. Voir
[Observer une limite](configuration.md#observer-une-limite).

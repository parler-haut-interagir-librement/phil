<!-- phil-dev source-sha256=647a4252d80335223d8e5e69072047db32b1cb0142a38c5ab79e1a6f00a721ff -->
# Limitation de débit

Les limiteurs de débit de ce bundle sont des limiteurs de `phil/rate-limit-bundle` qui comptent sur un compteur. Ce
bundle-là leur soumet les requêtes (par `#[RateLimit]`, la valeur par défaut de route `_rate_limit` ou l'option
`rate_limit` d'un formulaire) et répond aux refus ; celui-ci compte chaque requête sur le compteur du limiteur, pour la
clé de la requête. Un compteur reste un compteur : une fenêtre glissante donne une limite glissante (600 requêtes dans
la dernière heure, en compartiments d'une minute), une période du calendrier une allocation quotidienne, un cycle de
facturation l'allocation d'un forfait.

```yaml
phil_rate_limit:
    headers: draft

phil_usage_meter:
    meters:
        requests: { limit: 600, rolling: 3600, buckets: 60 }
        reports: { limit: 20, calendar: day }
    rate_limiters:
        api: { meter: requests, key: ip }
        members: { meter: requests, key: user }
        reports: { meter: reports, key: App\Usage\AccountKey, amount: 1 }
```

Chaque entrée de `rate_limiters` est un limiteur de ce nom, à côté de ceux de `phil_rate_limit.limiters` : deux
limiteurs d'un même nom font échouer la construction du conteneur. `PhilRateLimitBundle` doit être enregistré.

## Limiter un contrôleur

```php
use Phil\RateLimitBundle\Domain\Attribute\RateLimit;

#[RateLimit('api')]
final class SearchController
{
    #[RateLimit('reports', amount: 5)]
    public function report(): Response
    {
        // ...
    }
}
```

L'ordre des règles, la valeur par défaut de route, les formulaires et les noms inconnus sont ceux de
`phil/rate-limit-bundle`.

## Pour qui compte une requête

La `key` du limiteur, ou celle de la règle, est une stratégie de clé de `phil/rate-limit-bundle` : `ip` et `user` en
empreintes HMAC-SHA-256 sous son secret, `user_or_ip`, `ip_route`, ou un service qui implémente son `KeyResolver`. La
clé est le sujet du compteur : le stockage ne contient jamais d'adresse ni de courriel. Une stratégie qui ne trouve
aucune clé laisse la requête non comptée ; associez un limiteur `user` à un limiteur `ip`, ou prenez `user_or_ip`, pour
que les requêtes anonymes soient aussi limitées.

## Nouvelles tentatives et `Idempotency-Key`

Un limiteur avec `idempotency_key: true` ne compte qu'une fois une requête que le client renvoie avec le même en-tête
`Idempotency-Key` : un formulaire de paiement envoyé deux fois, un appel repris après un délai dépassé :

```yaml
phil_usage_meter:
    rate_limiters:
        orders: { meter: orders, key: user, idempotency_key: true }
```

```http
POST /orders HTTP/1.1
Idempotency-Key: "8e03978e-40d5-43e8-bc93-6894a57f9324"
```

- Seule une méthode qui change quelque chose reçoit une clé : POST, PUT, PATCH et DELETE. Une requête GET, HEAD ou
  OPTIONS est comptée chaque fois, quel que soit l'en-tête qu'elle porte, pour qu'un client ne puisse pas lire
  gratuitement.
- La valeur fait de 1 à 255 caractères ASCII imprimables, entre guillemets comme une chaîne de champ structuré ou non,
  sans guillemet ni barre oblique inverse à l'intérieur : `"abc"` et `abc` sont la même clé. Une autre valeur reçoit
  la réponse `400 Bad Request`. Une requête sans l'en-tête est comptée comme d'habitude.
- Le stockage ne garde jamais la valeur du client : il garde le HMAC-SHA-256, sous `idempotency_secret`, du nom du
  limiteur, de la méthode, de l'URI avec sa chaîne de requête, du SHA-256 du corps et de la valeur. La même clé sur une
  autre action, une autre URI ou un autre corps est une autre clé, et compte ; deux limiteurs à clé sur un même
  compteur comptent chacun la requête. Une clé est propre au sujet : les clés d'un client ne rencontrent donc jamais
  celles d'un autre.
- Une requête rejouée atteint quand même le contrôleur : le limiteur s'abstient seulement de la compter de nouveau.
  Gardez l'action elle-même idempotente, par le même en-tête, quand elle ne doit pas se produire deux fois.

Un limiteur sans `idempotency_key` ignore l'en-tête. Le code qui compte une action avec sa propre clé (un identifiant
de message, un identifiant de tâche) appelle lui-même `UsageMeter::consume()`.

## La réponse

Quand une requête ferait dépasser le quota à son sujet, rien n'est compté et `phil/rate-limit-bundle` répond
`429 Too Many Requests` avec `Retry-After` (les secondes jusqu'à ce que la fenêtre libère une partie de son
utilisation : la période suivante, ou le plus ancien compartiment qui quitte une fenêtre glissante) et ses en-têtes de
limite de débit : la limite du compteur pour ce sujet, ce qui reste, et les mêmes secondes comme remise à zéro. Une
requête comptée reçoit les en-têtes du limiteur à qui il reste le moins. Un limiteur dont le compteur n'a aucune limite
pour le sujet n'en ajoute aucun.

La limite est celle du sujet : les fournisseurs de limites et les fonctionnalités d'abonnement s'appliquent aux
limiteurs de débit comme à tout compteur, et un compte payant peut ainsi avoir droit à plus de requêtes qu'une adresse
anonyme.

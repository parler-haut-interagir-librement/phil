<!-- phil-dev source-sha256=646a3ba7eb82d45fadc0543e26833af630f71a0ba483ffee0abc6019a2fa9e1a -->
# Installation

## Prérequis

| Paquet | Version | Pourquoi |
|---|---|---|
| PHP | 8.5 ou plus récent | Les classes en lecture seule, les constantes typées, `new` sans parenthèses. |

La bibliothèque n'a aucun autre prérequis.

## Installer

```sh
composer require phil/poll
```

## Un premier dépouillement

Les règles du scrutin vérifient chaque bulletin au moment où il est déposé ; le dépouillement relit les bulletins, à
partir d'un tableau ou de n'importe quel itérable qu'un stockage lit en flux :

```php
use Phil\Poll\CountingMethods;
use Phil\Poll\PollRules;

$rules = PollRules::multiple(['saturday', 'sunday', 'monday'], min: 1, max: 2);

$ballot = $rules->ballot($request->toArray()['choices'] ?? []);   // InvalidBallot when it breaks the rules
$store->keep($ballot->choices);

$methods = CountingMethods::defaults();
$result = $methods->count(CountingMethods::defaultFor($rules->kind), $rules, $store->ballots());
```

`CountingMethods::defaults()` contient les quatre méthodes de la bibliothèque. Il ne garde aucun état entre deux
dépouillements : une seule instance sert chaque requête d'un *worker* de longue durée.

Dans Symfony, `phil/poll-bundle` garde les scrutins et leurs bulletins avec Doctrine, anonymes ou non, et les
dépouille sur demande.

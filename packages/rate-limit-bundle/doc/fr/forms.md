<!-- phil-dev source-sha256=bf1b89fe8eee09f62a3745679fcc3e7be06388eaab1e4ccc6fd3fdb63a3e29dd -->
# Formulaires

Le bundle limite les soumissions d'un formulaire par son nom, avec symfony/form installé.

```yaml
phil_rate_limit:
    limiters:
        contact: { policy: token_bucket, limit: 3, rate: { interval: '10 minutes' } }
    forms:
        contact: contact
```

```php
$form = $formFactory->createNamed('contact', ContactType::class);
$form = $formFactory->createNamed('signup', SignupType::class, options: ['rate_limit' => [new RateLimit('signups', key: 'ip_route')]]);
$form = $formFactory->createNamed('contact', ContactType::class, options: ['rate_limit' => false]);
```

L'option `rate_limit` contient les règles du formulaire, écrites comme l'est une valeur par défaut de route ; `null` ou
`true` prend celles que la section `forms` donne au nom du formulaire, et `false` aucune. Un formulaire limité par un
limiteur que rien ne déclare fait échouer la construction du conteneur.

Chaque soumission du formulaire racine consomme, valide ou non, sous la clé de la règle suivie du nom du formulaire. Un
refus n'est pas une page d'erreur : le formulaire reçoit une erreur, traduite dans le domaine `phil_rate_limit`, et
n'est pas valide, si bien que le contrôleur l'affiche de nouveau avec son message.

```text
Too many submissions of this form. Try again in 412 seconds.
```

La cause de l'erreur est l'exception `TooManyRequests`, et `RateLimitExceeded` est réparti comme pour tout refus.

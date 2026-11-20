<!-- phil-dev source-sha256=b45f04611e82ea226bd4ff596a54fedb8262885790d05027f31b9ce0e8a9cf26 -->
# Tests

## Les tests du bundle

La suite `tests/Unit` teste le format du jeton (génération sur tout l'alphabet, somme de contrôle, chaque forme mal
formée), la ligne et ses règles, le calculateur d'empreinte, le catalogue des portées, `ApiTokens` sur un stockage en
mémoire à heure fixe (création, durées de vie, recherche, révocation, renouvellement avec et sans grâce, purge,
vérification et dernière utilisation à débit limité, renouvellement de la clé, jetons divulgués), un même contrat pour les deux stockages, exécuté en mémoire et
sur Doctrine avec SQLite en mémoire, le gestionnaire de jetons par l'authentificateur `access_token` de Symfony, l'écouteur des rôles, les détails de
problème, le *voter* et l'écouteur de `#[RequiresScope]`, le point de terminaison de l'analyse de secrets de GitHub
avec une paire de clés qu'il fabrique, les intégrations, les quatre commandes, la page des membres et son formulaire, et la configuration et le câblage. La
suite `tests/Functional` démarre une application avec FrameworkBundle, SecurityBundle, TwigBundle, DoctrineBundle sur
SQLite, `phil/api-contract-bundle`, `phil/rate-limit-bundle` et `phil/usage-meter-bundle`. Elle suit un jeton de la
page des membres jusqu'à l'API (ses portées, son quota, sa révocation), à travers un rapport de l'analyse de secrets de GitHub et à travers les commandes.

Depuis la racine du monodépôt :

```sh
vendor/bin/phpunit packages/api-keys-bundle/tests
```

Le bundle est tenu à un score de mutation couvert de 100 % par Infection.

## Les cibles de fuzzing

`tests/Fuzz/token.php` donne au lecteur de jetons du texte que personne n'a écrit. Quoi qu'il lise, il retourne un
jeton qui se réécrit en le même texte, ou lève `MalformedToken`, et rien d'autre. `tests/Fuzz/github-leak-report.php` et
`tests/Fuzz/github-public-keys.php` font de même pour les lecteurs des rapports et des clés de GitHub : des
correspondances et des clés dans leurs bornes, ou `UnreadableReport`.

```sh
composer fuzz -- api-keys-bundle
```

## Le banc d'essai

`tests/Benchmark/AuthenticatorBench.php` mesure ce que paie chaque requête de l'API : la lecture d'un jeton,
l'empreinte à clé, une vérification sur un stockage en mémoire avec sa dernière utilisation à débit limité, et toute
l'authentification d'une requête, de l'en-tête jusqu'au jeton de sécurité par l'authentificateur `access_token` de Symfony et l'écouteur des rôles : environ 27 µs sur un
portable.

```sh
composer bench -- packages/api-keys-bundle/tests/Benchmark
```

## Tester une application qui l'utilise

Gardez les jetons en mémoire et donnez à l'environnement de test sa propre clé. Le stockage en mémoire est vidé entre
deux requêtes, comme en production sous un *worker* : un test qui lit ce qu'une requête antérieure a écrit utilise le
stockage Doctrine sur SQLite, ou crée son jeton par `ApiTokens` dans la même requête que celle du client.

```yaml
# config/packages/test/phil_api_keys.yaml
phil_api_keys:
    store: memory
    hash_key: 'a-test-key-of-thirty-two-bytes!!'
```

```php
$tokens = self::getContainer()->get(ApiTokens::class);
$issued = $tokens->create('alice@example.com', 'test', ['projects:read']);

$client->request('GET', '/api/projects', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$issued->plainText]);
```

Une `MockClock` comme `clock` du bundle fait passer un jeton au-delà de son expiration ou de son intervalle de limite
de débit.

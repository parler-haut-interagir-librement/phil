<!-- phil-dev source-sha256=5124cc7fed7bfc4d836277593d14f4275b7e4c6af82e8ba767d888e4ec6832f8 -->
# Tests

## Les tests du bundle

La suite `tests/Unit` teste le nom d'une file, la demande et ses instants, les statuts et les échecs ; les files de la
configuration et le workflow ; `Helpdesk` sur les dépôts DBAL du service d'assistance et des conversations avec SQLite
— l'ouverture, les demandes et les réponses du demandeur, la réponse d'un agent et son assignation, les files qu'un
agent travaille, le bureau et ses filtres, l'assignation, le déplacement, les transitions manuelles, une garde de
l'application, une demande fermée, les réponses types — ; le dépôt, ses instants en UTC, ses pages et l'effacement,
l'écouteur de schéma ; l'accès par rôle, les réponses types des gabarits, l'exportation et l'effacement, la notification
d'une assignation, les fonctions Twig et le lien d'un tableau de bord EasyAdmin ; et chaque définition que câble la
configuration.

La suite `tests/Functional` démarre un noyau avec FrameworkBundle, Symfony Security avec des membres en mémoire,
DoctrineBundle sur SQLite, le composant KeyManagement, TwigBundle et les recettes du kit, un tableau de bord EasyAdmin,
et phil/conversation-bundle, et sert les pages : une demande du membre à l'agent et retour, avec une réponse type,
résolue et fermée ; un agent qui prend, déplace et filtre ; les demandes des autres introuvables, le bureau refusé à un
membre qui ne travaille aucune file, les formulaires sans jeton refusés ; le bureau greffé sur le tableau de bord.

La suite `tests/Integration` exécute une demande par le bureau sur MySQL 8.4 et PostgreSQL 17, à `PHIL_TEST_MYSQL_URL`
et `PHIL_TEST_POSTGRESQL_URL`, et est ignorée sans eux.

Depuis la racine du monodépôt :

```sh
vendor/bin/phil-dev services up
eval "$(vendor/bin/phil-dev services env)"
vendor/bin/phpunit packages/helpdesk-bundle/tests
```

Le bundle est tenu à 100 % de mutants couverts par Infection.

## Tester une application qui utilise le service d'assistance

Dans un test de noyau, connectez-vous comme membre, puis comme agent d'une file, et suivez une demande par les pages :

```php
$client->loginUser($member);
$client->request('POST', '/help/new', ['_token' => 'csrf-token', 'queue' => 'billing', 'subject' => 'Help', 'body' => 'Please.'], server: ['HTTP_ORIGIN' => 'http://localhost']);

$client->loginUser($agent);
$client->request('GET', '/support-desk/');
```

Avec la protection CSRF sans état de FrameworkBundle dans un environnement de test, la valeur `csrf-token` passe quand la
requête nomme le site comme origine.

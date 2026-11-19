<!-- phil-dev source-sha256=93f5d3d02bb4c5934ac543ff13bfa1790a9e7a92955cafd0674297334fe6e087 -->
# Tests

## Les tests du bundle

La suite `tests/Unit` teste les identifiants et les modèles ; `Conversations` et `Reception` sur les dépôts DBAL avec
SQLite — conversations directes et de groupe, réglages de réception et blocages, vérification du participant, non lu,
sourdine, départ, boîte de réception et fil par page, événements, deux premiers messages au même instant — ; le sceau, son format et chaque texte altéré qu'il
refuse ; les dépôts, leurs instants en UTC, la conversation directe unique, l'effacement et l'écouteur de schéma ; la
rotation et sa commande ; les notifications, le sujet d'une notification, les mises à jour Mercure et un hub
injoignable, l'exportation et l'effacement de phil/privacy-bundle, le participant courant de Symfony Security, les
fonctions Twig et les attributs du temps réel ; et chaque définition que câble la configuration.

La suite `tests/Functional` démarre un noyau avec FrameworkBundle, DoctrineBundle sur SQLite, le composant KeyManagement
avec une clé locale, TwigBundle et les recettes du kit, et sert les pages : un membre écrit à un autre, qui lit et
répond ; un message refusé dit sur la page ; les conversations des autres introuvables ; les formulaires sans jeton
refusés ; la sourdine, les blocages et le départ ; les pages d'une longue conversation et d'une boîte de réception
pleine.

La suite `tests/Integration` exécute les dépôts sur MySQL 8.4 et PostgreSQL 17, à `PHIL_TEST_MYSQL_URL` et
`PHIL_TEST_POSTGRESQL_URL`, et est ignorée sans eux : les conversations conservées et lues par page, la conversation
directe unique et deux premiers messages au même instant dans une transaction ouverte, l'effacement et une rotation de la clé maîtresse.

Les tests du contrôleur Stimulus, dans `tests/js`, s'exécutent avec l'exécuteur de tests propre à Node, Stimulus étant
remplacé par une doublure par `register.mjs`. `tests/Functional/JavaScriptTest.php` les exécute avec les autres suites ;
sur une machine sans Node, il est ignoré et le dit.

Depuis la racine du monodépôt :

```sh
vendor/bin/phil-dev services up
eval "$(vendor/bin/phil-dev services env)"
vendor/bin/phpunit packages/conversation-bundle/tests
```

Le bundle est tenu à 100 % de mutants couverts par Infection.

## Tester une application qui utilise les conversations

Dans un test de noyau, démarrez les conversations par le service et lisez les pages comme un participant :

```php
$conversations = self::getContainer()->get(Conversations::class);
$conversation = $conversations->startDirect(ParticipantId::fromString('member-7'), ParticipantId::fromString('member-42'), 'Hello');

$client->loginUser($bob);
$client->request('GET', '/messages/'.$conversation->id);
```

Un environnement de test scelle avec un client local et une clé fixe de 32 octets, jamais une clé de production :

```yaml
# config/packages/test/key_management.yaml
key_management:
    clients:
        local: 'sodium://?keys[conversations]=a2tra2tra2tra2tra2tra2tra2tra2tra2tra2tra2s'
```

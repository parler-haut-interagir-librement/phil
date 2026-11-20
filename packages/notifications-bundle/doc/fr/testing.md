<!-- phil-dev source-sha256=d62e0d496433b13cbd1ec7433d8110437641255409b6a6cc3391861f4d1d9835 -->
# Tests

## Les tests du paquet

```sh
composer install
vendor/bin/phpunit --no-configuration tests
node --import ./tests/js/register.mjs --test tests/js
```

La suite unitaire, sous `tests/Unit`, s'exécute sans noyau ni réseau : le dépôt et les préférences DBAL sur SQLite en
mémoire, les autres sur des jeux de données d'essai en mémoire et d'enregistrement. L'expéditeur Web Push chiffre et
signe de vrais messages pour une clé de navigateur faite par OpenSSL, postés à un faux service de poussée qui les
accepte, les oublie ou les refuse. La suite fonctionnelle, sous
`tests/Functional`, démarre une application avec FrameworkBundle, DoctrineBundle sur SQLite, Twig et ses composants,
envoie une notification, rend la cloche et la liste, et la marque lue par un formulaire vérifié par la véritable
protection CSRF sans état. Avec Symfony UX Turbo, elle rend la cloche comme un élément permanent, envoie un formulaire
comme le fait Turbo et lit le nombre de non-lues. Un second noyau publie une diffusion vers Slack et Discord par les
ponts mêmes de Symfony Notifier, contre un client HTTP d'enregistrement qui répond comme leurs API. `FallbackTest` exécute le mode `first_success` contre
de faux transports qui acceptent, refusent ou lancent une exception sur commande, avec une horloge simulée, une fenêtre
de doublons dans un cache en mémoire et un limiteur en mémoire : le premier canal, le suivant et le dernier, un canal
sans contact sauté sans être appelé, un circuit qui s'ouvre et laisse passer sa sonde, un doublon dans la fenêtre, un
échec total puis sa nouvelle tentative avec le centre gardé une seule fois, la limite de débit et le type `urgent` qui la
contourne, un refus d'un transport et une autre exception qui remonte. Le bouton Web Push est rendu avec sa
configuration, et l'abonnement d'un navigateur gardé et oublié par ses points de terminaison. Les tests
Node couvrent les modules des contrôleurs — les mises à jour lues depuis Mercure et les messages éphémères construits à
partir d'elles, ce que le contrôleur Web Push lit du navigateur et envoie — et le *service worker*, exécuté avec un faux
`self` : ce qu'il affiche et ce qu'ouvre un clic.

`tests/Integration` exécute le contrat des stockages d'abonnements sur le stockage DBAL contre MySQL 8 et PostgreSQL 17 ;
il est sauté à moins que `PHIL_TEST_MYSQL_URL` ou `PHIL_TEST_POSTGRESQL_URL` soit défini, comme le fait
`phil-dev services test`. `tests/Fuzz/push-subscription.php` lit des abonnements que personne n'a écrits : chacun est lu
ou refusé par `InvalidPushSubscription`, jamais par une autre erreur (`composer fuzz -- push-subscription`).

Dans le monodépôt, les tests s'exécutent sur PHP 8.5 et 8.6, et Infection impose au paquet 100 % des mutants couverts.

## Les bancs d'essai

`tests/Benchmark/NotificationsBench.php` mesure le nombre de non-lues sur des tables de dix et de cent mille
notifications (il emploie l'index sur le destinataire et l'état, et reste stable à mesure que la table grandit), et la
livraison d'une notification au centre, nouvelle ou regroupée.

```sh
composer bench -- --filter=NotificationsBench
```

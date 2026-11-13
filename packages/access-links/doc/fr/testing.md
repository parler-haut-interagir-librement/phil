<!-- phil-dev source-sha256=37bfdd9ed0266df30de7d0e24803e0ed5bb6f5149d788d03d0c4f4eaab7f91af -->
# Tests

## Les tests du paquet

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite `tests/Unit` teste les jetons et leur lecteur strict, les empreintes à clé fixées et leurs clés ordonnées, les
invitations et leurs moments, les calendriers, les adresses des liens, `AccessLinks` sur une horloge figée à travers
l'ouverture, la clôture et l'expiration, les rappels par un bus Messenger, et un seul contrat exécuté sur les deux
magasins, celui de DBAL sur SQLite en mémoire. Dans le monodépôt, Infection tient le paquet à 100 % des mutants couverts.

La suite `tests/Integration` exécute le même contrat sur un vrai MySQL 8.4 et un vrai PostgreSQL 17, avec un rappel
réclamé et une réponse écrite depuis une autre connexion ; le test de chaque serveur lit son URL dans
`PHIL_TEST_MYSQL_URL` ou `PHIL_TEST_POSTGRESQL_URL` et est ignoré sans elle :

```sh
PHIL_TEST_MYSQL_URL='mysql://phil:phil@127.0.0.1:13306/phil_test?serverVersion=8.4' \
PHIL_TEST_POSTGRESQL_URL='postgresql://phil:phil@127.0.0.1:15432/phil_test?serverVersion=17' \
vendor/bin/phpunit packages/access-links/tests/Integration
```

## Fuzzing

La cible de fuzzing `link-token` donne au lecteur de jetons un texte arbitraire : tout ce qu'il accepte doit être le
texte lui-même et se décoder en exactement 32 octets qui s'encodent de nouveau en ce texte, et il ne lève rien d'autre
que `InvalidToken`.

```sh
composer fuzz -- link-token --budget=60
```

## Tester du code qui invite

Construisez `AccessLinks` sur un `InMemoryInvitationStore` et une `MockClock`, gardez le jeton du lien émis et avancez
l'horloge au-delà de la clôture :

```php
use Symfony\Component\Clock\MockClock;

$clock = new MockClock('2026-11-13 10:00:00');
$links = new AccessLinks(
    new InMemoryInvitationStore(),
    new TokenHasher(str_repeat('k', 32)),
    new LinkFormat(Url::fromString('https://events.example/rsvp')),
    $clock,
    new ReminderSchedule(['P1D']),
);

$issued = $links->invite('poll-1', 'member-1', $clock->now()->modify('+2 days'));
$clock->sleep(86400);
$links->remind($issued->invitation->id, $recordingSender);   // true: no answer yet
$links->respond($issued->token, 'yes');
```

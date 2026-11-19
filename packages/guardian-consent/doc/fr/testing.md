<!-- phil-dev source-sha256=50b71363ec33d4f3df7e6cb9d7efbf4e2d51739125c70716e1db999c58a7cfa8 -->
# Tests

## Les tests de la bibliothèque

La suite `tests/Unit` teste les dates du calendrier à travers les années, les années bissextiles et les fuseaux
horaires ; les années complètes écoulées la veille d'un anniversaire, le jour même et le lendemain, y compris pour un
anniversaire le 29 février selon les deux règles ; les juridictions, leurs bornes et les tranches qu'elles donnent ; les
identifiants, les fonctionnalités, les versions et les adresses à leurs bornes ; les jetons de confirmation et leurs
empreintes, avec rotation des clés ; les dépôts en mémoire ; et `GuardianConsent` sur une horloge figée — demandes,
réponses, jetons falsifiés, utilisés et expirés, retraits, passage de relais le jour de l'âge du consentement, balayage
et ses limites, événements qu'il répartit —, ainsi que l'accès et l'effacement.

Depuis la racine du monodépôt :

```sh
vendor/bin/phpunit packages/guardian-consent/tests/Unit
```

La cible de fuzzing `confirmation-token` tient le lecteur de jetons à son contrat :

```sh
vendor/bin/phil-dev fuzz confirmation-token
```

Dans le monodépôt, Infection tient la bibliothèque à 100 % des mutants couverts.

## Tester le code qui utilise les consentements

Construisez `GuardianConsent` sur les dépôts en mémoire, un notificateur qui garde ce qu'on lui remet et une horloge
figée, comme le `MockClock` de Symfony ; avancez l'horloge pour franchir un anniversaire ou un délai :

```php
use Symfony\Component\Clock\MockClock;

$clock = new MockClock('2026-11-14 12:00:00', 'America/Toronto');
$consent = new GuardianConsent(
    new InMemoryMinorStore(),
    new InMemoryRequestStore(),
    new InMemoryProofStore(),
    $notifier,                                        // keeps the token it is sent
    new TokenHasher(str_repeat('k', 32)),
    $clock,
);

$consent->register($member, CalendarDate::fromString('2012-11-20'));
$consent->request($member, $guardian, [new Feature('messages')], new NoticeVersion('v1'));
$consent->decide($notifier->token, [new Feature('messages')]);

self::assertTrue($consent->allows($member, new Feature('messages')));

$clock->modify('2026-11-20 00:00:00');               // the 14th birthday, in Quebec

self::assertSame(ConsentStatus::HandoverRequired, $consent->status($member, new Feature('messages')));
```

Les dépôts en mémoire implémentent `ResetInterface` : `reset()` les vide entre les tests qui partagent un conteneur.

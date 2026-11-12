<!-- phil-dev source-sha256=43e5b94e8784f61445c6124a5ea999c0e1d9ec7cf352aed508a7b1ba23731d41 -->
# phil/idempotency

**Chaque opération exécutée une fois par clé d'idempotence, quelles que soient les nouvelles tentatives.**

phil/idempotency retient la clé d'une intention — l'en-tête `Idempotency-Key` d'une requête d'API, l'identifiant que
porte un message, l'événement d'un webhook de paiement, le jeton d'un formulaire — et exécute l'opération une fois.
Une nouvelle tentative avec la même charge reçoit de nouveau la première réponse ; une clé réutilisée avec une autre
charge est refusée ; une nouvelle tentative qui arrive pendant que la première exécution se poursuit est invitée à
revenir après les secondes qui restent à sa réservation. Une exécution qui échoue laisse la même charge s'exécuter de
nouveau.

Chaque enregistrement est dans l'un de trois états : en cours, terminé ou échoué. L'état en cours expire de lui-même,
pour qu'un processus tué au milieu d'une exécution ne garde jamais sa clé. Seule l'empreinte SHA-256 de la charge est
conservée, jamais la charge.

C'est une bibliothèque PHP simple. Les stockages sont un port, avec un adaptateur pour Doctrine DBAL — une table, une
insertion atomique — et un pour tout pool de cache PSR-6, rendu atomique par le composant Lock de Symfony. Un
intergiciel Messenger traite un message reçu une fois par clé. Dans une application Symfony, `phil/api-contract-bundle`
y ajoute les contrôleurs `#[Idempotent]`.

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis et les dépendances facultatives. |
| [La règle](doc/fr/rule.md) | Les clés, les portées, les empreintes, les trois états et les quatre issues. |
| [La garde](doc/fr/guard.md) | `IdempotencyGuard` : `run()`, `begin()`, `complete()`, `fail()`, et leur usage pour une API, un webhook et un formulaire. |
| [Stockages](doc/fr/stores.md) | La table DBAL et le pool de cache sous verrou : atomicité, expiration, purge. |
| [Messenger](doc/fr/messenger.md) | L'intergiciel, ses clés, et ce qu'il fait d'un doublon. |
| [Exceptions](doc/fr/exceptions.md) | Ce que lève la bibliothèque, et la réponse HTTP de chacune. |
| [Points d'extension](doc/fr/extension-points.md) | Un stockage à soi, et le test de contrat qu'il peut étendre. |
| [Tests](doc/fr/testing.md) | Ce que fixe la suite, le test de concurrence et la cible de fuzzing. |
| [Référence](doc/fr/reference.md) | Chaque type public. |

## Démarrage rapide

```php
use Phil\Idempotency\{IdempotencyGuard, IdempotencyKey, IdempotencyScope, PayloadFingerprint, StoredResponse};
use Phil\Idempotency\Store\DbalIdempotencyStore;

$guard = new IdempotencyGuard(new DbalIdempotencyStore($connection, $clock), $clock, retention: 86_400);

$run = $guard->run(
    IdempotencyScope::of('client:42'),
    IdempotencyKey::fromHeader($request->headers->get(IdempotencyKey::HEADER)),
    PayloadFingerprint::ofRequest($request->getMethod(), $request->getRequestUri(), $request->getContent()),
    static fn (): StoredResponse => StoredResponse::of(201, ['Location' => ['/orders/7']], $orders->place($request)),
);
// $run->replayed: true when the stored answer was returned without running the operation.
```

## Prérequis

PHP 8.5 ou plus récent, `psr/clock` 1 et `symfony/uid` 8.2. Le stockage DBAL demande `doctrine/dbal` 4.5 ; le
stockage en cache `psr/cache` 3 et `symfony/lock` 8.2 ; l'intergiciel `symfony/messenger` 8.2.

## Contribuer

Les contributions sont les bienvenues dans le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).

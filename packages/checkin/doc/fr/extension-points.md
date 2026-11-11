<!-- phil-dev source-sha256=d2581d8f3c5d1a4612d4ae19b8a15e71882242dfd3f07c1a3f29c04cffe00901 -->
# Points d'extension

La bibliothèque a trois ports, des interfaces de `Phil\Checkin\Contract`. Leurs adaptateurs appartiennent à
l'application ou aux paquets qui étendent le noyau.

## `CredentialResolver`

Trouve le titre d'accès que représente un code :

```php
interface CredentialResolver
{
    public function resolve(CredentialCode $code): ?Credential;
}
```

Un résolveur répond null pour un code qu'il ne connaît pas. `CredentialResolvers` en interroge plusieurs dans leur ordre
et s'arrête au premier qui connaît le code : les billets, les inscriptions et les cartes de membre apportent ainsi
chacun le leur. Un résolveur peut lire une table, appeler un service, ou vérifier un code qui porte son titre signé en
lui-même. Quoi qu'il fasse, il compare les codes en temps constant quand il les compare lui-même, et ne les journalise
jamais.

```php
use Phil\Checkin\Contract\CredentialResolver;
use Phil\Checkin\CheckpointId;
use Phil\Checkin\Credential;
use Phil\Checkin\CredentialCode;
use Phil\Checkin\CredentialStatus;

final readonly class TicketResolver implements CredentialResolver
{
    public function __construct(
        private TicketRepository $tickets,
    ) {
    }

    public function resolve(CredentialCode $code): ?Credential
    {
        $ticket = $this->tickets->findOneByCodeHash(hash('sha256', $code->value));

        return null === $ticket ? null : Credential::of(
            'ticket-'.$ticket->id,
            $ticket->holderName,
            [CheckpointId::of($ticket->door)],
            $ticket->event->doorsOpenAt,
            $ticket->event->endsAt,
            maxEntries: 1,
            status: $ticket->refunded ? CredentialStatus::Revoked : CredentialStatus::Active,
        );
    }
}
```

## `CheckInLedger`

L'endroit où les enregistrements sont gardés, partagé par chaque appareil et chaque processus :

```php
interface CheckInLedger
{
    public function attendance(string $credentialId): Attendance;

    /** @param list<string> $credentialIds @return array<string, Attendance> */
    public function attendances(array $credentialIds): array;

    public function find(ScanId $id): ?CheckIn;

    public function append(CheckIn $checkIn): bool;
}
```

- `attendance()` replie les admissions et les sorties d'un titre d'accès : leur nombre est la version, les admissions
  sont les entrées, la dernière d'entre elles dit si le détenteur est à l'intérieur. `Attendance::restore()` la
  reconstruit à partir des valeurs stockées, et `Attendance::none()` est un titre jamais scanné.
- `attendances()` répond une entrée par identifiant demandé, pour les manifestes ; elle n'est jamais appelée avec une
  liste vide.
- `append()` est atomique. Elle retourne false, et n'inscrit rien, quand l'identifiant de lecture est déjà inscrit, ou
  quand l'enregistrement porte une séquence déjà prise pour son titre. Une table avec une clé primaire sur l'identifiant
  de lecture et une clé unique sur le titre et la séquence fait les deux : les refus ne portent aucune séquence, et la
  clé unique les ignore.
- `CheckIn::restore()` reconstruit un enregistrement stocké ; le code de la lecture n'est jamais stocké.

`InMemoryCheckInLedger` est le comportement de référence, pour les tests. `phil/checkin-bundle` fournit un registre sur
une connexion Doctrine.

## `CredentialCatalog`

Les titres d'accès qu'une application peut lister, pour les appareils hors ligne :

```php
interface CredentialCatalog
{
    /** @return iterable<CatalogEntry> */
    public function forCheckpoint(CheckpointId $checkpoint): iterable;

    public function find(string $credentialId): ?Credential;
}
```

- `forCheckpoint()` produit un `CatalogEntry` — un code et son titre — par code des titres qui peuvent être scannés au
  point de contrôle ; un générateur garde la mémoire constante. L'émetteur garde ceux qui ouvrent le point de contrôle
  et s'arrête au-delà de sa limite.
- `find()` donne un titre d'accès par son identifiant, pour décider de nouveau une lecture faite hors ligne ; null
  rejette la lecture.

Sans catalogue, les appareils n'enregistrent les entrées qu'en ligne.

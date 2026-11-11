<!-- phil-dev source-sha256=3cb9c47bd748ca8f7beb1827a8e771b6a685720eba5f8f73fc483e1b65387a44 -->
# Points d'extension

La bibliothèque a trois ports, tous des interfaces de `Phil\Listings\Contract`, que lit le `Responder`. Leurs
adaptateurs appartiennent à l'application.

## `BlockList`

Indique si l'un de deux membres a bloqué l'autre.

```php
interface BlockList
{
    public function isBlocked(MemberId $one, MemberId $other): bool;
}
```

La réponse est symétrique : vraie quand l'un ou l'autre membre a bloqué l'autre, de sorte qu'un membre qui bloque
quelqu'un ne reçoit pas ses réponses et ne peut pas répondre à ses annonces. Le répondeur la consulte avant une réponse,
puis de nouveau avant une acceptation, pour qu'un blocage fait entre-temps l'emporte.

## `MemberAges`

L'âge d'un membre, en années entières à un instant donné.

```php
interface MemberAges
{
    public function ageOf(MemberId $member, DateTimeImmutable $at): ?int;
}
```

Null quand l'application ne le connaît pas ; une annonce avec un âge minimal refuse alors le membre avec `age_unknown`.
Le répondeur ne le demande que pour une annonce avec un âge minimal, avant une réponse puis de nouveau avant une
acceptation. Un adaptateur calcule l'âge à partir d'une date de naissance qu'il conserve, ou répond à partir d'une
vérification en laquelle il a confiance, et n'a jamais besoin de donner la date elle-même à la bibliothèque.

```php
use DateTimeImmutable;
use Phil\Listings\Contract\MemberAges;
use Phil\Listings\MemberId;

final readonly class ProfileAges implements MemberAges
{
    public function __construct(
        private Profiles $profiles,
    ) {
    }

    public function ageOf(MemberId $member, DateTimeImmutable $at): ?int
    {
        $birthDate = $this->profiles->birthDateOf($member->value);

        return null === $birthDate ? null : $birthDate->diff($at)->y;
    }
}
```

## `ResponseStore`

L'endroit où vivent les réponses.

```php
interface ResponseStore
{
    public function countOpen(ListingId $listing): int;

    public function hasOpen(ListingId $listing, MemberId $respondent): bool;

    public function save(Response $response): void;
}
```

- `countOpen()` compte les réponses en attente et acceptées à une annonce (`Response::isOpen()`), pour la borne de
  réponses ouvertes du genre ; un index sur l'annonce et le statut la rend peu coûteuse.
- `hasOpen()` indique si un membre a une réponse ouverte à une annonce, pour que chaque membre en ait au plus une.
- `save()` écrit une réponse, nouvelle ou modifiée ; `Response::restore()` la reconstruit à partir de ses champs.

C'est l'adaptateur qui décide si un décompte et l'écriture qui le suit sont rendus atomiques. Sans verrou ni contrainte
d'unicité sur l'annonce, le répondant et les statuts ouverts, deux réponses envoyées au même instant peuvent toutes deux
passer les bornes ; un index unique fait plutôt échouer la seconde dans le stockage.

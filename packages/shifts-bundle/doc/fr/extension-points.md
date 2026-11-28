<!-- phil-dev source-sha256=3e5d282a8f23c9c59a0806c07c97aa9c22c85aa2ef55b1ac59efc14826a75c33 -->
# Points d'extension

Le bundle est dans ses versions `0.x` : ses services, ses routes et ses gabarits peuvent changer dans une version
mineure, ce que dit le journal des modifications. Ils sont destinés à être stables dès la `1.0`.

| Pour | Faites |
|---|---|
| Donner la personne connectée et ses rôles | Implémentez `Application\Contract\CurrentPerson`, et nommez le service dans `phil_shifts.person`. |
| Attribuer les places autrement | Implémentez `Phil\Shifts\Allocation\InterestAllocator`, et nommez le service dans `phil_shifts.allocator`. |
| Réagir à un changement | Écoutez les événements de phil/shifts sur le répartiteur d'événements. |
| Attribuer dans un *worker* | Acheminez `Application\Message\AllocateInterest` vers un transport Messenger. |
| Donner les dates de naissance des bénévoles | Implémentez `Application\Contract\BirthDates`, et nommez le service dans `phil_shifts.minors.birth_dates`. |
| Stocker ailleurs | Implémentez `Domain\Repository\RosterRepository`, `SwapRepository`, `GuardianRepository` et `AbsenceRepository`, et créez-leur des alias. |
| Changer une page | Remplacez un gabarit sous `templates/bundles/PhilShiftsBundle/`. |
| Changer un texte | Remplacez une clé du domaine `phil_shifts` dans les traductions de l'application. |
| Utiliser les services des pages | Injectez `Application\Rosters`, `Application\SwapBoard`, `Application\Minors` et `Application\Absences`. |

## La personne connectée

```php
use Phil\ShiftsBundle\Application\Contract\CurrentPerson;

final readonly class MemberPerson implements CurrentPerson
{
    public function __construct(private Members $members, private Security $security) {}

    public function current(): ?string
    {
        return $this->security->getUser()?->getUserIdentifier();
    }

    public function roles(): array
    {
        // The trainings of the member, read from the application: FIRST_AID, CASH…
        return $this->members->trainingsOf($this->current());
    }
}
```

```yaml
phil_shifts:
    person: App\Shifts\MemberPerson
```

## Le stockage

Un stockage à soi implémente les dépôts et tient leurs promesses : une écriture sur la révision lue qui retourne
`false` quand une autre est passée avant, les places interrogeables par bénévole, un tuteur par bénévole et une absence
par bénévole et par quart, les listes bornées. `Rosters` et `SwapBoard` prennent les dépôts par leurs interfaces :

```yaml
services:
    Phil\ShiftsBundle\Domain\Repository\RosterRepository: '@App\Shifts\RedisRosterRepository'
```

## Les gabarits

Chaque page produit le rendu d'un gabarit de `@PhilShifts`, avec les variables que son commentaire énumère :
`board.html.twig`, `shift.html.twig`, `mine.html.twig`, `swaps.html.twig`, `manage/index.html.twig`,
`manage/roster.html.twig`, `manage/form.html.twig`, et les parties `_shift.html.twig`, `_actions.html.twig` et
`_refused.html.twig`. Une application les enveloppe dans sa mise en page en les remplaçant.

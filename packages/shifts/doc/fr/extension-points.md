<!-- phil-dev source-sha256=98d8486c83ffe0887ba5db600bf3508622447cb87aab9d4368d9a11c51f120ca -->
# Points d'extension

La bibliothèque est dans ses versions `0.x` : les ports, les objets-valeurs et les exceptions peuvent changer dans une
version mineure, ce que dit le journal des modifications. Ils sont destinés à être stables dès la `1.0`.

| Port | Implémenté par | Rôle |
|---|---|---|
| `Allocation\InterestAllocator` | `FairAllocator`, ou celui de l'application | choisit qui reçoit les places libres parmi les intéressés |
| `Event\ShiftEvent` | les huit événements | dit ce qui a changé, par des identifiants |

## Un attributeur à soi

Un attributeur reçoit les intéressés dans l'ordre où ils ont exprimé leur intérêt, le nombre de places libres et les
minutes que chaque bénévole détient ailleurs, et retourne les bénévoles qu'il choisit. Il peut peser ce que la
bibliothèque ne sait pas : l'ancienneté, une formation, le souhait de travailler avec un ami. La liste ne garde que les
bénévoles retournés qui sont intéressés, une fois chacun, et pas plus que les places : un attributeur ne peut donc pas
remplir un quart au-delà de sa capacité.

```php
use Phil\Shifts\Allocation\InterestAllocator;

final readonly class TrainedFirstAllocator implements InterestAllocator
{
    public function __construct(private TrainingRecords $records) {}

    public function choose(array $interested, int $places, array $minutesHeld): array
    {
        $volunteers = array_map(static fn ($place) => $place->volunteer, $interested);
        usort($volunteers, fn (string $a, string $b): int => $this->records->isTrained($b) <=> $this->records->isTrained($a));

        return array_slice($volunteers, 0, $places);
    }
}
```

## Le stockage

La bibliothèque ne garde rien. Une application stocke un quart, ses places et les offres de son tableau d'échange avec
les formats — `ShiftFormat`, `RosterFormat`, `SwapFormat` — et les colonnes qu'elle interroge : la fenêtre du quart, les
bénévoles de ses places, le quart et l'état d'une offre. Les listes et les offres sont immuables ; deux requêtes qui
modifient en même temps la même liste sont départagées par le stockage, avec une révision comparée à chaque écriture.

## Les événements

Les événements d'un changement se trouvent dans `$changes` de la liste qu'une opération retourne. Ils ne portent que les
identifiants du quart et du bénévole — jamais un nom, une adresse ni une note —, de sorte qu'une application peut les
mettre dans une file d'attente tels quels ; un écouteur lit le reste dans le magasin. Une application les répartit une
fois la liste stockée.

## Les conflits et les rôles

La liste ne voit ni les autres quarts du bénévole ni l'origine des rôles : une application donne les rôles du bénévole à
chaque opération, et vérifie `Conflicts` avant une inscription, une affectation ou un échange.

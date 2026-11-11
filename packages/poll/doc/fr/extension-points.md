<!-- phil-dev source-sha256=7bc6d81758a0f4459763555e3a8a7f10acd5d4636b0b4a4a194c94e39aeda4b2 -->
# Points d'extension

La bibliothèque a un seul port, `Phil\Poll\Contract\CountingMethod`. Une application ajoute une façon de dépouiller en
l'implémentant, et la déclare à côté de celles de la bibliothèque dans `CountingMethods`.

## `CountingMethod`

```php
interface CountingMethod
{
    public function name(): string;

    public function supports(BallotKind $kind): bool;

    /**
     * @param iterable<Ballot> $ballots
     */
    public function count(PollRules $rules, iterable $ballots): Result;
}
```

- `name()` est ce qu'un scrutin stocke pour être dépouillé par la méthode : de 1 à 64 lettres ASCII minuscules,
  chiffres ou `_`, unique parmi les méthodes de l'application.
- `supports()` dit quelles sortes de bulletins la méthode dépouille ; `count()` lance `InvalidPoll` pour les autres.
- `count()` lit les bulletins une seule fois — ils peuvent venir d'un générateur sur une table — et ne garde aucun
  état entre deux dépouillements : une seule instance sert ainsi chaque requête d'un *worker* de longue durée. Elle
  lance `InvalidBallot` pour un choix qui n'est pas l'une des options des règles.

Un compte des premières préférences, pour des bulletins classés présentés comme une majorité simple :

```php
use Phil\Poll\BallotKind;
use Phil\Poll\Contract\CountingMethod;
use Phil\Poll\Exception\InvalidBallot;
use Phil\Poll\Exception\InvalidPoll;
use Phil\Poll\PollRules;
use Phil\Poll\Result;
use Phil\Poll\Standing;

final readonly class FirstPreferences implements CountingMethod
{
    public function name(): string
    {
        return 'first_preferences';
    }

    public function supports(BallotKind $kind): bool
    {
        return BallotKind::Ranked === $kind;
    }

    public function count(PollRules $rules, iterable $ballots): Result
    {
        if (!$this->supports($rules->kind)) {
            throw InvalidPoll::because('First preferences are counted on ranked ballots.');
        }

        $scores = array_fill_keys($rules->options, 0);
        $ballotCount = 0;
        $abstentions = 0;

        foreach ($ballots as $ballot) {
            ++$ballotCount;

            if ($ballot->isAbstention()) {
                ++$abstentions;

                continue;
            }

            $first = $ballot->choices[0];
            $rules->has($first) || throw InvalidBallot::because(sprintf('"%s" is not an option.', $first));
            ++$scores[$first];
        }

        arsort($scores);
        $standings = [];
        $place = 0;
        $previous = null;

        foreach ($scores as $option => $score) {
            $place = $score === $previous ? $place : \count($standings) + 1;
            $previous = $score;
            $standings[] = new Standing((string) $option, $score, $place);
        }

        return new Result($this->name(), $ballotCount, $abstentions, $standings);
    }
}
```

```php
use Phil\Poll\CountingMethods;
use Phil\Poll\Counting\Borda;
use Phil\Poll\Counting\ChoiceCount;
use Phil\Poll\Counting\InstantRunoff;
use Phil\Poll\Counting\Schulze;

$methods = new CountingMethods([new ChoiceCount(), new InstantRunoff(), new Borda(), new Schulze(), new FirstPreferences()]);
```

`phil/poll-bundle` déclare chaque service qui implémente `CountingMethod` à côté des méthodes de la bibliothèque.

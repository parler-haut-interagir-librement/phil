# Rules and ballots

## The kinds of ballot

| `BallotKind` | A ballot holds | Built by |
|---|---|---|
| `Single` | exactly one option | `PollRules::single($options, $abstention)` |
| `Multiple` | between `$min` and `$max` options, in no order | `PollRules::multiple($options, $min, $max, $abstention)` |
| `Ranked` | between `$min` and `$max` options, the first one preferred | `PollRules::ranked($options, $min, $max, $abstention)` |

`$min` defaults to 1 and `$max` to every option. `PollRules::of($kind, $options, $min, $max, $abstention)` builds the
rules of any kind, as a store keeps them.

## The options

A poll has 2 to 100 options (`PollRules::MAX_OPTIONS`). Each is identified by 1 to 64 ASCII letters, digits, `_` or
`-` (`PollRules::MAX_OPTION_LENGTH`), declared once; its label, its description and its translations belong to the
application. The declared order is kept: it is the order of the standings among tied options, and the last tie-break
of an instant runoff.

The bound on the options bounds the cost of a count: the pairwise count of the Schulze method grows with the square
of the options, its strongest paths with their cube.

## The bounds and the abstention

The bounds satisfy 1 ≤ min ≤ max ≤ the number of options; a single ballot holds exactly one choice. A ballot outside
them is refused.

A blank ballot — no choice at all — is an abstention when the rules accept one (`$abstention: true`) and refused
otherwise. A count counts it among the ballots and among the abstentions, and gives it to no option.

## Checking a ballot

`PollRules::ballot()` takes the choices as a form or a request gives them, `array<mixed>`, and returns a `Ballot` or
throws `InvalidBallot` when:

- the choices are not a list;
- a choice is not a string naming one of the options;
- a choice is given twice;
- the number of choices is outside the bounds, unless the ballot is blank and the abstention accepted.

A ranked ballot keeps the order given: the first choice is the preferred one. A ballot carries nothing about its
voter; who voted and whether that is kept is the application's.

`Ballot::of()` builds a ballot as given, for a store that reads back the choices it checked when they were cast. A
count checks again that each choice is an option, given once, but not the bounds: rules whose bounds were narrowed
after the vote still count the ballots cast before.

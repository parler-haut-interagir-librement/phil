# Results

## Counting

`Polls::results($poll)` counts the ballots of the poll by its method and returns the `Result` of `phil/poll`: the
ballots and abstentions counted, the standings with their shared places, the winners and, for an instant runoff, its
rounds. See the counting documentation of `phil/poll` for each method. `turnout($poll)` is the number of ballots cast.

A poll that fills several seats is counted into an election, with its rounds: see
[Single transferable vote](single-transferable-vote.md). [The voting interface](voting-interface.md) shows the results
to a viewer.

`results()` asks nobody: it is for code of the application that decides itself what to show. `resultsFor($poll,
$viewer)` counts for a viewer — `null` for someone not signed in — and throws `PollRefused` when the results are not
theirs to see yet. `maySeeResults($poll, $viewer)` answers the same question without counting.

## Visibility

The people the policy lets manage the poll always see its results. The others see them as the poll's
`ResultsVisibility` says:

| Case | Who sees the results |
|---|---|
| `Always` | Everyone, at any time. |
| `AfterVoting` | A voter once they have voted, anonymously or not, and everyone once the poll is closed. |
| `AfterClosing` | Everyone once the poll is closed, by hand or at its closing. |
| `Managers` | Nobody else. |

Showing the results before the end changes how people vote; `AfterClosing` is the usual choice for a decision,
`AfterVoting` for a poll of opinion.

## Cost

A count reads every ballot of the poll, streamed row by row from the table, and the library groups identical rankings.
Ten thousand ballots over ten options are counted in about ten milliseconds, plus the time of reading them. An
application that shows the results of large polls to many visitors names a cache pool under `results_cache`: a count
is then kept until the next ballot, and read from the pool in a few microseconds. See
[Configuration](configuration.md#the-cache-of-the-counts).

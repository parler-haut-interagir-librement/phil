# Results, retention and privacy

## Results

`Results::of($questionnaire, $subject)` counts the stored answers with `phil/poll`, one response at a time:

| Type | Result |
|---|---|
| `yes_no`, `single_choice`, `multiple_choice` | The count of each option |
| `ranking` | The points of each option by its places |
| `scale` | The distribution and the mean |
| `net_promoter` | The Net Promoter Score |
| `matrix` | The count of each column, row by row |

Free answers are never counted. The `ResultsReport` holds the number of responses, the minimum and the result of each
question counted, as `QuestionResult`.

Nothing is shown below the questionnaire's `results.minimum`, 5 by default: while fewer responses are counted, the
report is withheld and holds no question; a question fewer respondents answered is withheld on its own; a matrix is
withheld while its least answered row is below the minimum. An answer that no longer fits its question, such as an
option since removed, is left out.

`Results::public()` gives the same report to a respondent after answering, and throws `SurveyRefused` unless the
questionnaire's `results.public` is on; it is off by default. The ending page shows it only when the response was kept.

## Retention

A questionnaire's `retention_days` sets how long its responses are kept; without it, they are kept until removed. The
message `PurgeSurveyResponses`, which carries nothing, deletes the responses past their retention, with their addenda,
in batches. Its handler is idempotent: run twice, it deletes nothing the second time.

With Symfony Scheduler and Messenger, the schedule `phil_survey` sends it every day at `schedule.at`. With a lock
factory, one worker runs it however many consume the schedule; with a cache pool, the schedule keeps its last run and
catches up a missed one. Consume it with:

```sh
bin/console messenger:consume scheduler_phil_survey
```

## Privacy

With `phil/privacy-bundle`, the module `survey` takes part in a person's export and erasure, through its public
contracts:

- the export gives the identified responses of the person, their answers, computed variables, ending, addenda and
  instant, and the questionnaires the person took part in;
- the erasure deletes those responses, their addenda and the person's marks of participation.

Anonymous responses hold nothing that names a person, so neither finds them.

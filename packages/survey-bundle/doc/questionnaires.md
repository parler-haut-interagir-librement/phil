# Questionnaires

A `Questionnaire` is an immutable value: its sections and questions, its computed variables, its endings and its
quotas, and how it is answered. Building one checks it whole and throws `InvalidQuestionnaire`, naming the first rule
it breaks: a questionnaire that exists is valid.

## Its parts

| Part | What it holds | Bounds |
|---|---|---|
| `key` | The questionnaire's identifier, in its address | 1 to 64 lower-case letters, digits, `_` or `-` |
| `title`, `description` | What the respondent reads first | 255 and 5000 characters |
| `kind` | `survey`, `session_feedback`, `post_game_letter` or `routing` | `survey` by default |
| `anonymity` | `anonymous` or `identified` | `anonymous` by default |
| `pacing` | `question`, `section` or `all`: how many questions a step shows | `section` by default |
| `requires_member` | Whether only a signed-in member answers | always true when identified |
| `sections` | The sections in order, each with its questions | 1 to 50 sections, 1 to 100 questions each, 200 in all |
| `computed` | Variables computed from the answers, such as a score, as `phil/form-logic` writes its steps | 50 |
| `endings` | The end screens; the last has no condition | 1 to 50 |
| `quotas` | How many responses of a kind are taken | 50 |
| `retention_days` | How many days a response is kept; forever when absent | 1 to 3650 |
| `results` | `public`, whether respondents see the results after answering, and `minimum`, the responses counted before anything is shown | `false` and 5 by default |
| `window` | `opens_at` and `closes_at`, instants with their offset | both optional |
| `feedback` | `opens_before` and `closes_after`, in minutes around a session | 15 and 4320 by default |
| `addenda` | Whether a respondent may add to the response after sending it | `false` by default |

Each kind changes little: a `session_feedback` response is about a subject — a session the application names — and is
open only in that session's window; a `routing` questionnaire is one whose endings lead each respondent somewhere; a
`post_game_letter` is a questionnaire written after a game, usually identified and taking addenda.

## Question types

| Type | Answer | Checked by | Counted in results |
|---|---|---|---|
| `text`, `textarea`, `email` | Free text, `max_length` 1 to 10000 | `phil/forms-bundle` | No |
| `number` | An integer between `min` and `max` | The bundle | No |
| `date` | A date | `phil/forms-bundle` | No |
| `yes_no` | Yes or no | `phil/forms-bundle` | Count of yes and no, by `phil/poll` |
| `single_choice` | One of `choices` | `phil/poll` | Count of each option |
| `multiple_choice` | `min` to `max` of `choices` | `phil/poll` | Count of each option |
| `ranking` | `choices` in order, `min` to `max` of them | `phil/poll` | Points by place |
| `scale` | A mark from `min` to `max`, 2 to 100 marks | `phil/poll` | Distribution and mean |
| `net_promoter` | A mark from 0 to 10 | `phil/poll` | Net Promoter Score |
| `matrix` | One column of `choices` for each of `rows` | `phil/poll`, row by row | Count of each column, per row |

A choice has a `value` — 1 to 64 letters, digits, `_` or `-` — and a `label`. A question has 2 to 100 choices and a
matrix 1 to 50 rows. Every question has a `name`, a `label`, an optional `help` and `required`.

## Conditions

Conditions are stored as structures — JsonLogic objects of the subset `phil/form-logic` reads —, never as code or as
text to parse. They are read strictly and typed against the questions when the questionnaire is built:

- a question's `shown_when`, and its section's, read the questions before it whose type the browser reads too: every
  type except `ranking` and `matrix`. A hidden section hides all its questions; a question shows when both its
  section's condition and its own hold. The same steps run in the browser, through the `logic` controller of
  `phil/form-logic`, and on the server.
- an ending's `when` and a quota's `when` read every question — each row of a matrix as `question.row` — and every
  computed variable. Only the server evaluates them.

```json
{"name": "adults", "title": "About you", "shown_when": {">=": [{"var": "age"}, 18]}, "questions": []}
```

## The exchange format

`QuestionnaireJson` writes and reads a questionnaire as `phil-survey/1`:

```json
{"format": "phil-survey/1", "questionnaire": {"key": "welcome", "title": "Welcome", "sections": [], "endings": []}}
```

The reader is strict and bounded: at most 1 MiB and 64 levels, no unknown member, every rule of the model applied.
The JSON schema of the format is `schema/questionnaire.schema.json`, for editors and other tools; the bundle checks
more than the schema can say — unique names, typed conditions, the bounds of each type.

```sh
bin/console phil:survey:export welcome > welcome.json
bin/console phil:survey:import welcome.json --replace
```

`Questionnaires` saves, finds, removes, exports and imports questionnaires, and checks each ending's redirect against
the allowed origins before saving.

## Templates

A template is the data of a questionnaire without its key. `Questionnaires::templates()` lists them by name, and
`Questionnaires::fromTemplate('session_feedback', 'talk-42', 'Feedback on the keynote')` makes and saves a
questionnaire from one. The bundle gives `session_feedback`: two marks and two open questions, anonymous, its results
shown from three responses. Other templates come from providers, see [extension points](extension-points.md).

# CSV import

The questions of a questionnaire come from a CSV file through `phil/import-wizard`: one row per question, by its
identifier. A spreadsheet writes the file; the dry run shows each row as a question created, replaced or refused, with
the cell at fault, before anything is saved. The file follows the conventions shared with the questions of an
assessment, described by the [question files](https://github.com/parler-haut-interagir-librement/import-wizard/blob/main/doc/question-files.md)
of `phil/import-wizard`.

The file carries the questions only. The conditions, the computed variables, the endings, the quotas and the settings
of a questionnaire stay in the [exchange format](questionnaires.md#the-exchange-format) and in the code that builds it.

## The file

| Column | Headers also read | Holds |
|---|---|---|
| `question_id` | `id`, `identifier`, `question id`, `name`, `identifiant`, `nom`, `code` | the name of the question, required: 1 to 64 lower-case letters, digits or `_`, from a letter |
| `type` | `question type`, `kind`, `type de question` | one of the twelve types of [Questionnaires](questionnaires.md#question-types), required |
| `label` | `question`, `question text`, `text`, `stem`, `libellé`, `énoncé`, `texte` | what respondents read, required, at most 255 characters |
| `required` | `mandatory`, `obligatoire`, `requis`, `requise` | yes or no, no when empty |
| `help` | `hint`, `help text`, `description`, `aide`, `indice` | a help text, at most 1000 characters |
| `choices` | `options`, `answers`, `columns`, `choix`, `réponses`, `colonnes` | the options of a single or multiple choice and of a ranking, the columns of a matrix: 2 to 100, separated by `\|` |
| `rows` | `matrix rows`, `statements`, `lignes`, `lignes de la matrice` | the rows of a matrix: 1 to 50, separated by `\|` |
| `min`, `max` | `minimum`, `lowest`, `minimum value`, `valeur minimale`; `maximum`, `highest`, `maximum value`, `valeur maximale` | the bounds of a number, the marks of a scale — both required —, how many options a multiple choice or a ranking holds |
| `max_length` | `length`, `maximum length`, `longueur`, `longueur maximale` | the length of a free text, 1 to 10000 characters |
| `section` | `section name`, `section id`, `page`, `partie` | the name of the section the question goes to |
| `section_title` | `section title`, `section heading`, `titre de section`, `titre de la section` | the title of that section, should the row create it |

Headers are read in any case, with or without accents, and the delimiter is detected: a comma, a semicolon, a tab or a
bar. The template of the file is `TemplateFile::csv(QuestionCsvProfile::profile())`.

A choice is written `value = label` when the text before its first `=` is a value — 1 to 64 ASCII letters, digits,
`_` or `-` — and a label follows; the value is what the conditions, the quotas and the results read. Any other choice
is its label alone, and its value is its position: `1`, `2`, `3`… The rows of a matrix are written the same way.

```csv
question_id,type,label,required,choices,rows,min,max,section,section_title
overall,scale,How was the day?,yes,,,1,5,day,The day
liked,multiple_choice,What did you like?,no,talks = Talks|food = Food|The venue,,,2,day,
grid,matrix,Rate each room,,Bad|Fair|Good,hall = Main hall|annex = Annex,,,rooms,The rooms
again,yes_no,Would you come again?,,,,,,,
```

`liked` holds the options `talks`, `food` and `3`; `again`, which names no section, goes to the last section, `rooms`.
A label holding `|` cannot be written in this file: the exchange format has no such limit.

## Into a questionnaire

A file is imported into the questionnaire of a key, which may not exist yet.

- A row whose identifier the questionnaire holds **replaces** that question where it is, and keeps its condition, which
  the file does not carry. When the row names another section, the question moves to the end of that one, and a
  section left without questions is removed.
- A row with a new identifier is **added** at the end of the section it names, created after the others when the
  questionnaire has none of that name, its title the row's `section_title` or its name. A row that names no section
  goes to the last section.
- A questionnaire that does not exist is **created** under the title given — its key when none —, its questions in the
  sections the rows name, those that name none in the section `questions` under the questionnaire's title, and with the
  one ending `thanks`, "Thank you". Its endings, conditions and settings are then changed by the exchange format or by
  code.

The questionnaire is checked as a whole with each row: a row it would refuse — a question whose new type a condition
can no longer read, the 201st question, the 101st of a section — is refused with the reason, and the other rows are
saved. The questionnaire is saved once per batch, its redirects checked as `Questionnaires::save()` checks them; a save
refused refuses every row of its batch. A question replaced keeps the answers already given: a change of its type or its
choices changes how they read, as an import of the exchange format does.

## The dry run and the import

`QuestionImports` reads the file within its bounds, makes the dry run and writes the plan the person confirmed. It keeps
nothing between calls; the bundle registers it as a public service.

```php
use Phil\ImportWizard\Output\RejectedRowsFile;
use Phil\SurveyBundle\Application\Import\QuestionImports;

$plan = $imports->plan($imports->read($csv), 'day-feedback');

// Show $plan->preview() — create, update or refuse, line by line — and keep $plan->fingerprint with the confirmation.

$plan = $imports->plan($imports->read($csv), 'day-feedback'); // when the confirmation comes in another request
$result = $imports->commit($plan, 'day-feedback', 'Feedback on the day', $confirmedFingerprint);
$toCorrect = RejectedRowsFile::csv($plan, $result->rejections);
```

The profile carries `QuestionRowRule`: in the dry run, each row is read as a question, and a row the questionnaire
would refuse on its own is refused in the preview with the cell at fault, before anything is written. A row the
questionnaire refuses as a whole is refused by the import. A profile read back from JSON names the rule: pass
`[QuestionRowRule::class]` to `ImportProfile::fromJson()`.

The command does the same from a terminal: the dry run by default, line by line, and the import with `--write`. It
fails when a row is refused.

```sh
bin/console phil:survey:import-questions questions.csv day-feedback --title="Feedback on the day"
bin/console phil:survey:import-questions questions.csv day-feedback --title="Feedback on the day" --write
```

## Bounds

`QuestionCsvProfile::limits()` reads at most 1 MiB, 300 rows after the header and 32 cells a row; a file beyond them, a
quote left open or a file that is not UTF-8 is refused whole, before any row is planned. The cells are bounded by the
profile: 255 characters for a label and a section title, 1000 for a help text, 64 for an identifier and a section, and
the cells of choices and rows by the longest list they may hold. A reason never repeats a text of the row.

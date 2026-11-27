# Question files

The questions of a quiz and of a survey come from CSV files that follow the same conventions, so that a person who
writes one in a spreadsheet can write the other. Each file is an `ImportProfile` of the package that owns the
questions, with its own target; this library reads the file, makes the dry run and writes the plan, as for any other
type.

| Questions of | Profile | Type | Target | Its documentation |
|---|---|---|---|---|
| An assessment: the quiz questions of a bank, with their right answers | `Phil\Assessment\Import\QuestionImportProfile` | `assessment-question` | `QuestionImportTarget`, the versioned bank | [CSV import](https://github.com/parler-haut-interagir-librement/assessment/blob/main/doc/csv-import.md) of `phil/assessment` |
| A survey: the questions of a questionnaire, with their sections | `Phil\SurveyBundle\Application\Import\QuestionCsvProfile` | `survey-question` | `QuestionImportTarget`, one questionnaire | [CSV import](https://github.com/parler-haut-interagir-librement/survey-bundle/blob/main/doc/csv-import.md) of `phil/survey-bundle` |

## The shared conventions

- **One row per question**, by its identifier in the column `question_id`, the natural key: a row whose identifier is
  known updates that question — a new version in a bank, a question replaced in a questionnaire —, any other row creates
  one.
- **`type`** names the type of the question, among the types of its package, written as that package writes them:
  `single_choice`, `multiple_choice`, `true_false`, `scale`…
- **The text of the question** is required: `stem` for an assessment, `label` for a survey; both read the headers
  `question`, `question text` and `text`, and a survey reads `stem` too.
- **Lists in one cell are separated by `|`**: the choices, the items to order, the pairs to match, the rows of a matrix,
  the tags. Blank items are dropped and every item is trimmed. A text holding `|` cannot be written in these files:
  the JSON formats of the packages have no such limit.
- **A choice without a value of its own takes its position**, `1`, `2`, `3`…, which the cell of the right answers of an
  assessment names. A survey also reads `value = label`, the value being what its conditions and results read; in an
  assessment, `=` separates the premise and the target of a matching pair.
- **Yes or no** is read as every boolean column of this library reads it: `yes`, `y`, `no`, `n`, `true`, `false`, `1`, `0`,
  `oui`, `o`, `non`, in any case.
- **Headers in English or in French**, in any case, with or without accents; the template of a profile, from
  `TemplateFile::csv()`, writes the column names.
- **Each row is checked as its package checks a question.** The profile carries a `RowRule` that reads the row as a
  question in the dry run: a row its package would refuse is refused in the preview, with the cell at fault and a
  reason that never repeats a text of the row. What only the target can say — a bank that holds the question with
  another type, a questionnaire that would break a rule with the row — is said by the import, line by line.
- **The refused rows come back** as a CSV file from `RejectedRowsFile`, each with its line and its errors, to correct
  and import again.

## Bounds

The file is read within the `SourceLimits` the application gives; the profile of a survey gives its own,
`QuestionCsvProfile::limits()`: 1 MiB, 300 rows after the header and 32 cells a row. Each column of both profiles
bounds its cells — an identifier of 64 characters, a text of the question, the cell of a list by the longest list its
package accepts. A file beyond its bounds is refused whole, before any row is planned.

## Formats not read

GIFT and QTI files are not read by these profiles. An adapter can read them as a `RowSource` that gives the cells of
the conventions above, or write the questions through the JSON formats of the packages.

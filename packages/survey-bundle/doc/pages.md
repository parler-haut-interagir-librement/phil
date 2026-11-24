# Pages

The bundle's pages work without JavaScript; Stimulus adds the pace and the progress.

## Routes

```yaml
# config/routes/phil_survey.yaml
phil_survey:
    resource: '@PhilSurveyBundle/config/routes.php'
    prefix: /survey
```

| Route | Method and path | What it does |
|---|---|---|
| `phil_survey_show` | `GET /{key}` | The questionnaire, or why it is refused; `?subject=` names a session |
| `phil_survey_submit` | `POST /{key}` | Takes the response: the ending, the redirect with `303`, or the form again with its errors, `422` |
| `phil_survey_addendum` | `POST /{key}/addendum` | Adds an addendum with the receipt's token |

A refused response answers `409`, an untrusted form `403`, an unknown key `404`. The forms are checked by Symfony's
stateless CSRF protection, which needs no session: the container does not build while `framework.csrf_protection` is
off.

## Templates

| Template | Variables |
|---|---|
| `@PhilSurvey/questionnaire.html.twig` | `questionnaire`, `subject`, `values`, `errors`, `refused`, `token` |
| `@PhilSurvey/_question.html.twig` | One question, its value and its error |
| `@PhilSurvey/ending.html.twig` | `questionnaire`, `receipt`, `results`, `token` |
| `@PhilSurvey/results.html.twig`, `@PhilSurvey/_result.html.twig` | A `ResultsReport`, and one `QuestionResult` |
| `@PhilSurvey/addendum.html.twig` | `questionnaire`, `refused` |

The questionnaire page says its anonymity before the first question, lists every error at the top with a link to its
question, and marks each question's fieldset with `aria-invalid` and `aria-describedby`. A ranking gives each option
a select of its place; a matrix is a table of radio buttons. The texts are in the translation domain `phil_survey`, in
English and French. Override a template under `templates/bundles/PhilSurveyBundle/`.

## The progress controller

The Stimulus controller `progress`, registered as `phil--survey-bundle--progress` by the package's `assets/`, sits on
the form beside the `logic` controller of `phil/form-logic`. With the pacing `question` or `section`, it shows one step
at a time with Previous and Next and a progress bar, skips the questions the logic hides, and checks the controls of a
step before leaving it. With the pacing `all`, or without JavaScript, every question is on one page.

The logic hides with the `hidden` attribute; the controller puts the steps it sets aside `inert` instead, so the two
never undo each other.

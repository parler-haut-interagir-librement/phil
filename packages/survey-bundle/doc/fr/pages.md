<!-- phil-dev source-sha256=5410498d39dbc6af10b94a87bfb8d256be5d922fa383ff05c329708a017d5c69 -->
# Pages

Les pages du bundle fonctionnent sans JavaScript ; Stimulus ajoute le rythme et la progression.

## Routes

```yaml
# config/routes/phil_survey.yaml
phil_survey:
    resource: '@PhilSurveyBundle/config/routes.php'
    prefix: /survey
```

| Route | Méthode et chemin | Effet |
|---|---|---|
| `phil_survey_show` | `GET /{key}` | Le questionnaire, ou la raison de son refus ; `?subject=` nomme une séance |
| `phil_survey_submit` | `POST /{key}` | Reçoit la réponse : la fin, la redirection avec `303`, ou de nouveau le formulaire avec ses erreurs, `422` |
| `phil_survey_addendum` | `POST /{key}/addendum` | Ajoute un addendum avec le jeton du reçu |

Une réponse refusée répond `409`, un formulaire non fiable `403`, une clé inconnue `404`. Les formulaires sont
vérifiés par la protection CSRF sans état de Symfony, qui n'a pas besoin de session : le conteneur ne se construit pas
tant que `framework.csrf_protection` est désactivé.

## Gabarits

| Gabarit | Variables |
|---|---|
| `@PhilSurvey/questionnaire.html.twig` | `questionnaire`, `subject`, `values`, `errors`, `refused`, `token` |
| `@PhilSurvey/_question.html.twig` | Une question, sa valeur et son erreur |
| `@PhilSurvey/ending.html.twig` | `questionnaire`, `receipt`, `results`, `token` |
| `@PhilSurvey/results.html.twig`, `@PhilSurvey/_result.html.twig` | Un `ResultsReport`, et un `QuestionResult` |
| `@PhilSurvey/addendum.html.twig` | `questionnaire`, `refused` |

La page du questionnaire dit son anonymat avant la première question, liste toutes les erreurs en haut avec un lien vers
leur question, et marque l'ensemble de champs de chaque question avec `aria-invalid` et `aria-describedby`. Un
classement donne à chaque option une liste de sélection de son rang ; une matrice est un tableau de boutons radio. Les
textes sont dans le domaine de traduction `phil_survey`, en anglais et en français. Remplacez un gabarit sous
`templates/bundles/PhilSurveyBundle/`.

## Le contrôleur progress

Le contrôleur Stimulus `progress`, enregistré sous le nom `phil--survey-bundle--progress` par les `assets/` du paquet,
se place sur le formulaire à côté du contrôleur `logic` de `phil/form-logic`. Avec le rythme `question` ou `section`,
il affiche une étape à la fois avec Précédent et Suivant et une barre de progression, saute les questions que la
logique masque, et vérifie les contrôles d'une étape avant de la quitter. Avec le rythme `all`, ou sans JavaScript,
toutes les questions sont sur une seule page.

La logique masque avec l'attribut `hidden` ; le contrôleur met plutôt `inert` les étapes qu'il écarte : les deux ne se
défont donc jamais l'un l'autre.

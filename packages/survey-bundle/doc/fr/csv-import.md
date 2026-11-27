<!-- phil-dev source-sha256=4bae7202531b54fe883f7cd852e162b39d7f05c5d67b3261cc4d87d60df6799c -->
# Importation CSV

Les questions d'un questionnaire viennent d'un fichier CSV par `phil/import-wizard` : une ligne par question, selon son
identifiant. Un tableur écrit le fichier ; l'essai à blanc montre chaque ligne comme une question créée, remplacée ou
refusée, avec la cellule en cause, avant que rien soit enregistré. Le fichier suit les conventions communes avec les
questions d'une évaluation, que décrivent les
[fichiers de questions](https://github.com/parler-haut-interagir-librement/import-wizard/blob/main/doc/question-files.md)
de `phil/import-wizard`.

Le fichier ne porte que les questions. Les conditions, les variables calculées, les fins, les quotas et les réglages
d'un questionnaire restent dans le [format d'échange](questionnaires.md#le-format-déchange) et dans le code qui le
construit.

## Le fichier

| Colonne | En-têtes aussi lus | Contenu |
|---|---|---|
| `question_id` | `id`, `identifier`, `question id`, `name`, `identifiant`, `nom`, `code` | le nom de la question, obligatoire : 1 à 64 lettres minuscules, chiffres ou `_`, à partir d'une lettre |
| `type` | `question type`, `kind`, `type de question` | l'un des douze [types de questions](questionnaires.md#types-de-questions), obligatoire |
| `label` | `question`, `question text`, `text`, `stem`, `libellé`, `énoncé`, `texte` | ce que lisent les répondants, obligatoire, 255 caractères au plus |
| `required` | `mandatory`, `obligatoire`, `requis`, `requise` | oui ou non, non si vide |
| `help` | `hint`, `help text`, `description`, `aide`, `indice` | un texte d'aide, 1000 caractères au plus |
| `choices` | `options`, `answers`, `columns`, `choix`, `réponses`, `colonnes` | les options d'un choix unique ou multiple et d'un classement, les colonnes d'une matrice : 2 à 100, séparées par `\|` |
| `rows` | `matrix rows`, `statements`, `lignes`, `lignes de la matrice` | les lignes d'une matrice : 1 à 50, séparées par `\|` |
| `min`, `max` | `minimum`, `lowest`, `minimum value`, `valeur minimale` ; `maximum`, `highest`, `maximum value`, `valeur maximale` | les bornes d'un nombre, les repères d'une échelle — les deux obligatoires —, le nombre d'options que contient un choix multiple ou un classement |
| `max_length` | `length`, `maximum length`, `longueur`, `longueur maximale` | la longueur d'un texte libre, 1 à 10000 caractères |
| `section` | `section name`, `section id`, `page`, `partie` | le nom de la section où va la question |
| `section_title` | `section title`, `section heading`, `titre de section`, `titre de la section` | le titre de cette section, si la ligne doit la créer |

Les en-têtes se lisent en majuscules ou en minuscules, avec ou sans accents, et le délimiteur est détecté : une virgule,
un point-virgule, une tabulation ou une barre. Le gabarit du fichier est
`TemplateFile::csv(QuestionCsvProfile::profile())`.

Un choix s'écrit `value = label` quand le texte avant son premier `=` est une valeur — 1 à 64 lettres ASCII, chiffres,
`_` ou `-` — et qu'un libellé suit ; la valeur est ce que lisent les conditions, les quotas et les résultats. Tout
autre choix est son libellé seul, et sa valeur est sa position : `1`, `2`, `3`… Les lignes d'une matrice s'écrivent de
la même façon.

```csv
question_id,type,label,required,choices,rows,min,max,section,section_title
overall,scale,How was the day?,yes,,,1,5,day,The day
liked,multiple_choice,What did you like?,no,talks = Talks|food = Food|The venue,,,2,day,
grid,matrix,Rate each room,,Bad|Fair|Good,hall = Main hall|annex = Annex,,,rooms,The rooms
again,yes_no,Would you come again?,,,,,,,
```

`liked` contient les options `talks`, `food` et `3` ; `again`, qui ne nomme aucune section, va à la dernière section,
`rooms`. Un libellé qui contient `|` ne peut pas s'écrire dans ce fichier : le format d'échange n'a pas cette limite.

## Dans un questionnaire

Un fichier est importé dans le questionnaire d'une clé, qui peut ne pas exister encore.

- Une ligne dont l'identifiant est dans le questionnaire **remplace** cette question là où elle est, et garde sa
  condition, que le fichier ne porte pas. Quand la ligne nomme une autre section, la question passe à la fin de
  celle-ci, et une section laissée sans question est retirée.
- Une ligne dont l'identifiant est nouveau est **ajoutée** à la fin de la section qu'elle nomme, créée après les autres
  quand le questionnaire n'en a aucune de ce nom, son titre étant le `section_title` de la ligne ou son nom. Une ligne
  qui ne nomme aucune section va à la dernière section.
- Un questionnaire qui n'existe pas est **créé** sous le titre donné — sa clé à défaut —, ses questions dans les
  sections que nomment les lignes, celles qui n'en nomment aucune dans la section `questions` sous le titre du
  questionnaire, et avec l'unique fin `thanks`, « Thank you ». Ses fins, ses conditions et ses réglages se changent
  ensuite par le format d'échange ou par le code.

Le questionnaire est vérifié en entier avec chaque ligne : une ligne qu'il refuserait — une question dont le nouveau
type n'est plus lisible par une condition, la 201e question, la 101e d'une section — est refusée avec la raison, et les
autres lignes sont enregistrées. Le questionnaire est enregistré une fois par lot, ses redirections vérifiées comme
`Questionnaires::save()` les vérifie ; un enregistrement refusé refuse chaque ligne de son lot. Une question remplacée
garde les réponses déjà données : un changement de son type ou de ses choix change la façon dont elles se lisent,
comme le fait une importation du format d'échange.

## L'essai à blanc et l'importation

`QuestionImports` lit le fichier dans ses limites, fait l'essai à blanc et écrit le plan que la personne a confirmé. Il
ne garde rien entre les appels ; le bundle l'enregistre comme un service public.

```php
use Phil\ImportWizard\Output\RejectedRowsFile;
use Phil\SurveyBundle\Application\Import\QuestionImports;

$plan = $imports->plan($imports->read($csv), 'day-feedback');

// Show $plan->preview() — create, update or refuse, line by line — and keep $plan->fingerprint with the confirmation.

$plan = $imports->plan($imports->read($csv), 'day-feedback'); // when the confirmation comes in another request
$result = $imports->commit($plan, 'day-feedback', 'Feedback on the day', $confirmedFingerprint);
$toCorrect = RejectedRowsFile::csv($plan, $result->rejections);
```

Le profil porte `QuestionRowRule` : dans l'essai à blanc, chaque ligne est lue comme une question, et une ligne que le
questionnaire refuserait à elle seule est refusée dans l'aperçu avec la cellule en cause, avant que rien soit écrit.
Une ligne que le questionnaire refuse dans son ensemble est refusée par l'importation. Un profil relu depuis le JSON
nomme la règle : passez `[QuestionRowRule::class]` à `ImportProfile::fromJson()`.

La commande fait la même chose depuis un terminal : l'essai à blanc par défaut, ligne par ligne, et l'importation avec
`--write`. Elle échoue quand une ligne est refusée.

```sh
bin/console phil:survey:import-questions questions.csv day-feedback --title="Feedback on the day"
bin/console phil:survey:import-questions questions.csv day-feedback --title="Feedback on the day" --write
```

## Limites

`QuestionCsvProfile::limits()` lit au plus 1 Mio, 300 lignes après l'en-tête et 32 cellules par ligne ; un fichier qui
les dépasse, un guillemet resté ouvert ou un fichier qui n'est pas en UTF-8 est refusé en entier, avant qu'aucune ligne
soit planifiée. Les cellules sont bornées par le profil : 255 caractères pour un libellé et un titre de section, 1000
pour un texte d'aide, 64 pour un identifiant et une section, et les cellules de choix et de lignes par la plus longue
liste qu'elles peuvent contenir. Une raison ne répète jamais un texte de la ligne.

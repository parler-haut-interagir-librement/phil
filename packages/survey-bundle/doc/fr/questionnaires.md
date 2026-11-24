<!-- phil-dev source-sha256=a0f8536e5cd644361505d32003193f1d59c599ec2bb5f23a2d458530ffcb541c -->
# Questionnaires

Un `Questionnaire` est une valeur immuable : ses sections et ses questions, ses variables calculées, ses fins et ses
quotas, et la façon d'y répondre. En construire un le vérifie en entier et lance `InvalidQuestionnaire`, qui nomme la
première règle enfreinte : un questionnaire qui existe est valide.

## Ses parties

| Partie | Ce qu'elle contient | Bornes |
|---|---|---|
| `key` | L'identifiant du questionnaire, dans son adresse | 1 à 64 lettres minuscules, chiffres, `_` ou `-` |
| `title`, `description` | Ce que le répondant lit en premier | 255 et 5000 caractères |
| `kind` | `survey`, `session_feedback`, `post_game_letter` ou `routing` | `survey` par défaut |
| `anonymity` | `anonymous` ou `identified` | `anonymous` par défaut |
| `pacing` | `question`, `section` ou `all` : combien de questions affiche une étape | `section` par défaut |
| `requires_member` | Si seul un membre connecté répond | toujours vrai quand il est identifié |
| `sections` | Les sections dans l'ordre, chacune avec ses questions | 1 à 50 sections, 1 à 100 questions chacune, 200 en tout |
| `computed` | Les variables calculées à partir des réponses, comme un pointage, telles que `phil/form-logic` écrit ses étapes | 50 |
| `endings` | Les écrans de fin ; le dernier n'a aucune condition | 1 à 50 |
| `quotas` | Combien de réponses d'un genre sont acceptées | 50 |
| `retention_days` | Combien de jours une réponse est conservée ; pour toujours si absent | 1 à 3650 |
| `results` | `public`, si les répondants voient les résultats après avoir répondu, et `minimum`, le nombre de réponses comptées avant que rien soit montré | `false` et 5 par défaut |
| `window` | `opens_at` et `closes_at`, des instants avec leur décalage | tous deux facultatifs |
| `feedback` | `opens_before` et `closes_after`, en minutes autour d'une séance | 15 et 4320 par défaut |
| `addenda` | Si un répondant peut compléter la réponse après l'avoir envoyée | `false` par défaut |

Chaque genre change peu de choses : une réponse `session_feedback` porte sur un sujet — une séance que l'application
nomme — et n'est ouverte que dans la fenêtre de cette séance ; un questionnaire `routing` est un questionnaire dont les
fins mènent chaque répondant quelque part ; un `post_game_letter` est un questionnaire écrit après une partie,
habituellement identifié et acceptant des addenda.

## Types de questions

| Type | Réponse | Vérifiée par | Comptée dans les résultats |
|---|---|---|---|
| `text`, `textarea`, `email` | Texte libre, `max_length` de 1 à 10000 | `phil/forms-bundle` | Non |
| `number` | Un entier entre `min` et `max` | Le bundle | Non |
| `date` | Une date | `phil/forms-bundle` | Non |
| `yes_no` | Oui ou non | `phil/forms-bundle` | Décompte des oui et des non, par `phil/poll` |
| `single_choice` | Un des `choices` | `phil/poll` | Décompte de chaque option |
| `multiple_choice` | De `min` à `max` des `choices` | `phil/poll` | Décompte de chaque option |
| `ranking` | Les `choices` dans l'ordre, de `min` à `max` d'entre eux | `phil/poll` | Points par rang |
| `scale` | Une note de `min` à `max`, de 2 à 100 notes | `phil/poll` | Répartition et moyenne |
| `net_promoter` | Une note de 0 à 10 | `phil/poll` | Indice de promotion net (Net Promoter Score) |
| `matrix` | Une colonne des `choices` pour chacune des `rows` | `phil/poll`, ligne par ligne | Décompte de chaque colonne, par ligne |

Un choix a une `value` — 1 à 64 lettres, chiffres, `_` ou `-` — et un `label`. Une question a de 2 à 100 choix et une
matrice de 1 à 50 lignes. Chaque question a un `name`, un `label`, une `help` facultative et `required`.

## Conditions

Les conditions sont stockées comme des structures — des objets JsonLogic du sous-ensemble que lit `phil/form-logic` —,
jamais comme du code ni comme du texte à analyser. Elles sont lues strictement et typées d'après les questions quand le
questionnaire est construit :

- le `shown_when` d'une question, et celui de sa section, lisent les questions qui la précèdent dont le type est aussi
  lu par le navigateur : tous les types sauf `ranking` et `matrix`. Une section masquée masque toutes ses questions ;
  une question s'affiche quand la condition de sa section et la sienne sont vraies. Les mêmes étapes s'exécutent dans
  le navigateur, par le contrôleur `logic` de `phil/form-logic`, et sur le serveur.
- le `when` d'une fin et celui d'un quota lisent toutes les questions — chaque ligne d'une matrice sous la forme
  `question.row` — et toutes les variables calculées. Seul le serveur les évalue.

```json
{"name": "adults", "title": "About you", "shown_when": {">=": [{"var": "age"}, 18]}, "questions": []}
```

## Le format d'échange

`QuestionnaireJson` écrit et lit un questionnaire sous la forme `phil-survey/1` :

```json
{"format": "phil-survey/1", "questionnaire": {"key": "welcome", "title": "Welcome", "sections": [], "endings": []}}
```

Le lecteur est strict et borné : 1 Mio et 64 niveaux au plus, aucun membre inconnu, chaque règle du modèle appliquée.
Le schéma JSON du format est `schema/questionnaire.schema.json`, pour les éditeurs et les autres outils ; le bundle
vérifie plus que ce que le schéma peut dire — noms uniques, conditions typées, bornes de chaque type.

```sh
bin/console phil:survey:export welcome > welcome.json
bin/console phil:survey:import welcome.json --replace
```

`Questionnaires` enregistre, trouve, supprime, exporte et importe des questionnaires, et vérifie la redirection de
chaque fin d'après les origines autorisées avant d'enregistrer.

## Gabarits

Un gabarit est les données d'un questionnaire sans sa clé. `Questionnaires::templates()` les liste par nom, et
`Questionnaires::fromTemplate('session_feedback', 'talk-42', 'Feedback on the keynote')` crée et enregistre un
questionnaire à partir de l'un d'eux. Le bundle fournit `session_feedback` : deux notes et deux questions ouvertes,
anonyme, ses résultats montrés à partir de trois réponses. D'autres gabarits viennent de fournisseurs ; voyez les
[points d'extension](extension-points.md).

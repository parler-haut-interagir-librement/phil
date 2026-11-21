<!-- phil-dev source-sha256=f0d6241447f27e6b4f7658f7ff33f65b9bd6ad78c090373a98eef22f0a9b001e -->
# Tâches et suggestions

## Une tâche est une sorte

Une sorte de tâche nomme ce que fait la tâche (`draft.rewrite`, `draft.proofread`, `character.describe`) en 64 lettres
minuscules, chiffres, `_`, `.` ou `-` au plus, en commençant par une lettre. La configuration donne à chaque sorte sa
plateforme, son modèle, ses options et sa réserve de jetons, et, pour une tâche qui demande plus que du texte, sa sortie,
sa structure et ses outils ([Sorties, flux continu et outils](outputs.md)), ainsi que la finalité que le registre des
usages énonce ([Transparence et vie privée](transparency.md)) :

```yaml
phil_ai:
    tasks:
        draft.rewrite: { platform: main, model: 'the-model', options: { max_tokens: 1024 } }
        character.describe: { platform: main, model: 'the-small-model', options: { temperature: 0.9 } }
```

## Les invites sont des traductions

Les invites d'une sorte sont deux messages du domaine de traduction `phil_ai` : `<kind>.system`, les instructions,
facultatif, et `<kind>.user`, la demande. Elles sont lues dans la locale que demande la tâche, avec les replis du
traducteur, et les paramètres de la tâche remplissent les espaces réservés écrits `{name}` :

```yaml
# translations/phil_ai.en.yaml
draft.rewrite.system: 'You are a careful editor. Keep the author''s voice and the {genre} tone.'
draft.rewrite.user: 'Rewrite this passage: {text}'
```

```yaml
# translations/phil_ai.fr.yaml
draft.rewrite.user: 'Réécris ce passage : {text}'
```

Ici, la demande française se replie sur les instructions anglaises. Un catalogue ICU (`phil_ai+intl-icu.en.yaml`)
accepte les pluriels et les sélections : `Invent {count, plural, one {one name} other {# names}}.` Une sorte sans
invite utilisateur dans la locale ni dans ses replis échoue avec `MissingPrompt` avant que quoi que ce soit soit compté
ou appelé.

Écrire les invites comme des traductions les garde là où les traducteurs travaillent déjà, versionnées avec
l'application. Cela permet aussi d'interroger un modèle dans la langue où l'on veut sa réponse.

## Exécuter une tâche

```php
use Phil\AiBundle\Application\AiTasks;
use Phil\AiBundle\Application\TaskRequest;

$suggestion = $tasks->run(new TaskRequest(
    kind: 'draft.rewrite',
    subject: 'project-42',             // whom the task is charged to
    locale: $request->getLocale(),
    parameters: ['genre' => 'fantasy', 'text' => $passage],
    owner: 'member-7',                 // the person it runs for; null for a task of the application
));
```

`run()` produit le rendu des invites, choisit la clé, réserve le quota, appelle le modèle, écrit le journal, règle le
quota et retourne une `Suggestion` ; `stream()` fait de même avec la réponse par morceaux ([Flux continu](outputs.md#flux-continu)) :

| Propriété | Signification |
|---|---|
| `id` | L'identifiant de la tâche dans le journal, un UUID v7 |
| `kind`, `subject` | Ce qui a été demandé, et le sujet à qui c'est facturé |
| `text` | Ce que le modèle a écrit ; vide pour des données structurées, une image ou de la parole |
| `output` | Ce que la tâche a demandé : `text`, `structured`, `image` ou `speech` |
| `data` | La sortie structurée, un objet de la classe de la tâche ou un tableau ; `null` autrement |
| `toolCalls` | Les outils que le modèle propose d'appeler, sous forme de `ProposedToolCall`, aucun exécuté |
| `media` | L'image ou la parole générée, sous forme de `Media` ; `null` autrement |
| `tokens` | Les jetons lus et écrits, tels que la plateforme les a comptés |
| `cost` | Le coût en millionièmes de la devise configurée |
| `keySource` | `application` ou `user` |
| `proposedAt` | Le moment où la tâche a commencé |

## Proposer, puis valider

Le bundle n'applique rien de ce que dit une tâche, et n'exécute aucun outil qu'elle propose. L'application montre la
suggestion (à côté du passage, sous forme de différences, comme brouillon que la personne peut modifier) et indique au
bundle ce qu'il en est advenu :

```php
$tasks->accept($id);         // applied as proposed
$tasks->acceptEdited($id);   // applied after the person changed it
$tasks->reject($id);         // set aside
```

Une suggestion est décidée une seule fois. Une deuxième décision échoue avec `AlreadyDecided`, et un identifiant que
le journal ne contient pas, avec `UnknownSuggestion`. Les décisions s'additionnent dans les totaux du journal (combien
de suggestions sont gardées, modifiées ou mises de côté) sans en garder un seul mot.

Le texte, les données, les appels d'outils et les médias d'une suggestion vivent seulement dans la `Suggestion`.
L'application les garde dans son propre brouillon, sa session ou son formulaire entre deux requêtes, et décide par
l'identifiant. `Suggestion::disclosure()` dit ensuite comment l'œuvre qui la reprend a été faite
([Divulgation de ce qui a été fait](transparency.md#divulgation-de-ce-qui-a-été-fait)).

## Échecs

Quand la plateforme échoue (refus, limite de débit, injoignable), la tâche est journalisée comme échouée, sans jetons,
la réservation qu'elle a faite sur le quota du sujet est rendue (la tâche, ses jetons et sa borne de coût), et
`TaskFailed` est levée avec l'exception du fournisseur comme exception précédente. Son message nomme la tâche et la
classe de l'exception du fournisseur, jamais le message du fournisseur, qui peut citer l'invite.

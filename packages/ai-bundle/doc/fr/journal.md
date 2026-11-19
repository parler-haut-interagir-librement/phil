<!-- phil-dev source-sha256=9a9f34cfbdba87b10af21ef3cfba266e1ab1737705a974f67687cda39539f230 -->
# Le journal

## Ce qui est enregistré

Chaque tâche qui a atteint le modèle occupe une ligne de `phil_ai_journal` :

| Colonne | Signification |
|---|---|
| `id` | L'identifiant de la tâche, un UUID v7, qui est aussi celui de la suggestion |
| `kind` | La sorte de tâche |
| `output` | Ce qu'elle a demandé : `text`, `structured`, `image` ou `speech` |
| `subject` | Le sujet à qui la tâche a été facturée |
| `platform`, `model` | L'endroit où elle s'est exécutée |
| `key_source` | `application` ou `user` |
| `outcome` | `succeeded` ou `failed` |
| `input_tokens`, `output_tokens` | Les jetons comptés par la plateforme; 0 pour une tâche échouée |
| `cost_micros`, `currency` | Le coût tiré de la liste de prix |
| `started_at`, `duration_ms` | Le début de l'appel, et sa durée |
| `decision`, `decided_at` | `pending`, `accepted`, `edited` ou `rejected`, et le moment de la décision |

Une tâche refusée avant son appel (sorte inconnue, invite manquante, aucune clé, quota atteint) n'est pas journalisée.

## Ce qui n'est jamais enregistré

L'invite, ses paramètres, la réponse, la clé de la personne, le propriétaire : rien de cela n'atteint le journal, ni
aucun message d'exception du bundle. Ce qu'une personne a écrit et ce qu'un modèle a proposé appartiennent à
l'application, qui les garde avec le texte auquel ils s'appliquent, ou nulle part.

## Coûts

La liste de prix donne à chaque modèle son prix en millionièmes de la devise configurée, par million de jetons lus et
par million de jetons écrits, et par appel pour un modèle qui facture à l'image ou à la requête. Le coût d'une tâche est
arrondi au millionième supérieur. Un modèle sans prix coûte 0, ce qui est utile pour un modèle local. Le journal garde la devise de chaque ligne, et les totaux additionnent les coûts
par devise.

## Totaux

```php
$totals = $tasks->totals('project-42', new DateTimeImmutable('first day of this month midnight'), new DateTimeImmutable('first day of next month midnight'));

$totals->tasks;        // tasks journaled in the span
$totals->failed;       // of which failed
$totals->tokens;       // Tokens: input and output
$totals->costs;        // ['USD' => 1234567], micros by currency
$totals->decisions;    // ['pending' => 1, 'accepted' => 7, 'edited' => 3, 'rejected' => 2]
```

La période va de son début inclus à sa fin exclue. Le stockage du journal les additionne : le journal Doctrine
exécute une seule requête groupée par résultat, décision et devise, sur l'index du sujet et du début, quel que soit le
nombre de lignes, et le journal en mémoire additionne ses entrées.

## Effacement

```php
$tasks->erase('project-42');   // how many rows were forgotten
```

Quand le compte ou le projet que nomme un sujet est effacé, son journal disparaît avec lui. Avec `phil/privacy-bundle`, le journal est son module `ai`, qui exporte et efface les
lignes d'une personne concernée ([Demandes de vie privée](transparency.md#demandes-de-vie-privée)).

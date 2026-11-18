<!-- phil-dev source-sha256=eb5933496c56ca3e9fb19838685fde2b232c443f16712fc6b08856fa6c1d3c2a -->
# Le modèle de données d'exécution SCORM

`Phil\ElearningPackages\Runtime` contient le modèle de données auquel un environnement d'exécution SCORM compare les
appels du contenu : les éléments de SCORM 1.2 (modèle de données CMI 3.4) et de SCORM 2004 4e édition, leurs types,
vocabulaires, intervalles et règles de lecture et d'écriture, les codes d'erreur de chaque version, l'API d'exécution
avec ses états, et ce que fait la plateforme au début et à la fin d'une session. C'est du PHP simple, qui ne garde aucun
état entre les appels : une plateforme y construit son environnement d'exécution, quoi qu'il transporte les appels — une
page qui relaie l'API du navigateur, un test, un outil de migration qui rejoue des données de suivi.

```php
use Phil\ElearningPackages\Runtime\CmiAttempt;
use Phil\ElearningPackages\Runtime\CmiCommit;
use Phil\ElearningPackages\Runtime\CmiData;
use Phil\ElearningPackages\Runtime\Entry;
use Phil\ElearningPackages\Runtime\LaunchSettings;
use Phil\ElearningPackages\Runtime\ScormVersion;

$data = new CmiData(ScormVersion::Scorm2004, $kept);       // the values an earlier commit kept
CmiAttempt::begin($data, new LaunchSettings('learner-42', masteryScore: 0.8), Entry::Resume);

$errors = CmiCommit::apply($data, $valuesFromTheBrowser); // ['cmi.learner_id' => 404], the others kept
$end = CmiAttempt::end($data);                            // total time, status, suspended or ended

$end->status->completion;  // 'completed'
$end->status->success();   // true
$end->status->score;       // 0.9
$end->attemptEnded;        // true: the next launch begins a new attempt
$store->save($data->values());
```

## Les éléments

`DataModel::definitions()` donne chaque élément d'une version, par nom, chaque index d'une collection s'écrivant `n` :
`cmi.interactions.n.id`. Une `ElementDefinition` dit qui peut le lire et l'écrire (`Access`), et ce que sa valeur peut
être : une expression régulière que sa valeur entière satisfait, un nombre de caractères au plus, un intervalle pour un
nombre, et ce qu'il lit avant d'être fixé. Les bornes sont les plus petits maximums permis de chaque spécification :

| | SCORM 1.2 | SCORM 2004 |
|---|---|---|
| `suspend_data` | 4 096 caractères | 64 000 caractères |
| Emplacement | `cmi.core.lesson_location`, 255 | `cmi.location`, 1 000 |
| Objectifs, interactions | 100, 250 | 100, 250 |
| Objectifs d'une interaction, bonnes réponses | 10, 10 | 10, selon le type d'interaction : 1 à 10 |
| Commentaires de l'apprenant, de la plateforme | `cmi.comments`, 4 096 caractères | 250, 100 |
| Temps | `CMITime` `HH:MM:SS.SS`, `CMITimespan` `HHHH:MM:SS.SS` | `time` de l'ISO 8601 de 1970 à 2038, `timeinterval` de l'ISO 8601 |
| Notes | brute, minimum et maximum de 0 à 100, ou vide | normalisée de -1 à 1 ; brute, minimum et maximum, tout réel |

Les réponses des interactions de SCORM 2004 sont vérifiées selon le type de leur interaction : `DataModel::responsePattern()`
donne le motif de `true-false`, `choice`, `sequencing`, `likert`, `matching` et `numeric` ; les réponses de `fill-in`,
`long-fill-in`, `performance` et `other` sont bornées en longueur.

Les motifs sont écrits de sorte que PHP, avec le modificateur `u`, et JavaScript, avec le drapeau `u`, les lisent de la
même façon. `DataModel::describe()` donne le modèle de données tel que le navigateur le lit — les éléments, les
collections et leurs bornes, les éléments qui répondent à `_children`, les bonnes réponses et les motifs des réponses, et
les messages des codes d'erreur —, de sorte qu'un environnement d'exécution dans le navigateur répond à un appel comme le
fera le serveur.

## Lire et écrire

`CmiData` contient les valeurs d'une tentative, par élément. `get()` et `set()` répondent comme `GetValue()` et
`SetValue()` : un `RuntimeResult` avec la valeur lue, et le code d'erreur de la version, `0` s'il n'y en a pas, avec un
court diagnostic.

- Un élément que le modèle de données ne définit pas, la lecture d'un élément en écriture seule, l'écriture d'un élément
  en lecture seule ou d'un mot-clé sont refusés ; `_children`, `_count` et `cmi._version` reçoivent une réponse.
- Une collection grandit un élément à la fois : un index au-delà du prochain libre est refusé, tout comme un index
  au-delà de la borne.
- En SCORM 2004, un élément d'un objectif ou d'une interaction exige d'abord son identifiant, et une réponse le type de
  son interaction (`408`) ; l'identifiant d'un objectif est unique et ne change jamais, tout comme celui d'un objectif
  d'une interaction parmi les autres de l'interaction (`351`).
- Une valeur est vérifiée selon le motif, la longueur et l'intervalle de son élément ; un nombre s'écrit sous forme de
  texte.
- Un élément jamais fixé se lit comme la chaîne vide en SCORM 1.2, et répond `403` en SCORM 2004 à moins d'avoir une
  valeur par défaut : `cmi.completion_status` et `cmi.success_status` se lisent `unknown`, `cmi.total_time` `PT0H0M0S`.
- En SCORM 2004, `cmi.completion_status` est évalué selon `cmi.completion_threshold` et `cmi.progress_measure`, et
  `cmi.success_status` selon `cmi.scaled_passing_score` et `cmi.score.scaled`, quand la plateforme les fixe.
- Les données d'une tentative sont bornées en octets dans leur ensemble, 1 Mio par défaut : une écriture au-delà est
  refusée.

`put()` est l'écriture propre à la plateforme — `cmi.core.student_id`, `cmi.total_time` —, qui vérifie que l'élément
existe, non son accès. `values()` donne ce qu'il faut conserver ; un nouveau `CmiData` le recharge, et refuse par
`RuntimeRefused` une valeur qui n'est pas du texte ou un élément que la version ne définit pas.

`CmiCommit::apply()` applique une validation — les éléments écrits depuis la dernière, dans l'ordre où ils ont été
écrits — comme le ferait `set()`, conserve les valeurs que le modèle de données accepte, et donne les autres avec leurs
codes d'erreur. Une validation nomme `CmiCommit::MAX_VALUES` éléments au plus.

## Les codes d'erreur

`ScormError` contient les codes de chaque version et leurs messages : `0`, `101`, `201`, `202`, `203`, `301` et `401` à
`405` en SCORM 1.2 ; `0`, `101` à `104`, `111` à `113`, `122`, `123`, `132`, `133`, `142`, `143`, `201`, `301`, `351`,
`391` et `401` à `408` en SCORM 2004. La même erreur a un autre numéro dans chacune : `ScormError::for()` donne le
numéro d'une `Mistake` dans une version.

| Erreur | SCORM 1.2 | SCORM 2004 |
|---|---|---|
| Élément non défini | 401 | 401 |
| Élément en lecture seule écrit, mot-clé écrit | 403, 402 | 404 |
| Élément en écriture seule lu | 404 | 405 |
| Mauvais type, hors intervalle | 405 | 406, 407 |
| Dépendance non établie | 405 | 408 |
| Index au-delà du prochain, collection pleine | 201, 101 | 351 |
| Index au-delà du compte lu | 201 | 301 |
| `_children` ou `_count` d'un élément qui n'en a pas | 202, 203 | 301 |
| Élément sans valeur lu | la chaîne vide, `0` | 403 |

## L'API

`ScormSession` répond aux appels d'une session comme le feraient `LMSInitialize()`… ou `Initialize()`… : `"true"` ou
`"false"`, la valeur lue, et `getLastError()`, `getErrorString()` et `getDiagnostic()`. Un appel avant `initialize()` ou
après `terminate()` est refusé — `301` en SCORM 1.2 ; `122`, `123`, `132`, `133`, `142`, `143`, `112`, `113` en SCORM
2004 —, un argument qui n'est pas la chaîne vide répond `201`, un second `initialize()` `101` ou `103`, un `initialize()`
après `terminate()` `101` ou `104`. `commit()` et `terminate()` appellent les fermetures données, qui conservent les
données, et répondent `101`, `391` ou `111` quand elles disent ne pas avoir pu.

## Le début et la fin d'une session

`CmiAttempt::begin()` fixe ce que la plateforme remet à un SCO, à partir de `LaunchSettings` : l'identifiant et le nom de
l'apprenant, le crédit, le mode, l'entrée (`Entry`), les données de lancement, la note de maîtrise —
`cmi.student_data.mastery_score` sur cent en SCORM 1.2, `cmi.scaled_passing_score` en SCORM 2004 —, le seuil d'achèvement
de SCORM 2004, le temps alloué et son action ; elle oublie la sortie et le temps de session de la session précédente, et
fixe le `cmi.core.lesson_status` de SCORM 1.2 à `not attempted` à une première session.

`CmiAttempt::end()` ajoute le temps de session au temps total, comme l'écrit chaque version (`Duration` : centièmes de
seconde, une année de l'ISO 8601 comptée comme 365 jours et un mois comme 30, bornée à 9 999 heures). En SCORM 1.2, un
statut que le contenu n'a jamais fixé devient `completed`, et avec une note de maîtrise et une note brute en mode crédit,
le statut devient `passed` ou `failed` selon la note. Une sortie à `suspend` suspend la tentative ; sinon SCORM 2004 y met
fin, et SCORM 1.2 y met fin une fois complétée, réussie ou échouée. Le `SessionEnd` donne le statut, le temps total, si la
tentative est suspendue ou terminée, et la demande de navigation de SCORM 2004, s'il y en a une.

`RuntimeStatus` lit où en est l'apprenant dans les mots de SCORM 2004 quelle que soit la version — le `lesson_status` de
SCORM 1.2 tel que l'ADL le fait correspondre : `passed` est complétée et réussie, `failed` complétée et échouée, `browsed`
incomplète — avec la note normalisée de -1 à 1 par `Score` : la note normalisée quand elle est fixée, sinon la note brute
entre son minimum et son maximum, sur cent en SCORM 1.2 quand ils ne sont pas fixés.

## Ce que le modèle de données laisse à la plateforme

- Le séquencement et la navigation de SCORM 2004 : `adl.nav.request` est conservé et rendu par `SessionEnd` ; la validité
  des demandes, `adl.nav.request_valid.*`, revient à la plateforme, qui la fixe avec `put()`.
- L'endroit où les valeurs sont conservées, les tentatives et leurs numéros, la progression de l'apprenant dans un cours.
- Les formats des réponses des interactions `fill-in`, `long-fill-in`, `performance` et `other`, bornées seulement.

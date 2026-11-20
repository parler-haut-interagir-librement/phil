<!-- phil-dev source-sha256=7d127227b1eb5d470fa133ac709c801680257f50c244e8cdb21d961945eba775 -->
# Séquencement SCORM

SCORM 2004 permet à un cours de dire comment l'apprenant s'y déplace : quelles activités peuvent être choisies, lesquelles
suivent par enchaînement, quand l'une est sautée ou désactivée, comment les résultats des enfants se cumulent dans leur
parent, quels objectifs sont partagés entre des activités. C'est l'IMS Simple Sequencing, tel que SCORM 2004 4e édition
l'applique. SCORM 1.2 a un dispositif plus petit : les prérequis d'un élément.

La bibliothèque lit les deux dans un arbre d'activités immuable, et les exécute avec un séquenceur : une machine à états
déterministe qui prend un état et une demande de navigation, et renvoie un nouvel état avec l'activité à lancer. Elle ne
garde rien entre deux demandes ; la plateforme conserve l'état.

## Lire l'arbre d'activités

```php
use Phil\ElearningPackages\Sequencing\SequencingReader;

$tree = new SequencingReader()->read($manifestXml);           // the default organization
$tree = new SequencingReader()->read($manifestXml, 'org-2');  // another one

$tree->root;              // the organization, as an Activity
$tree->get('quiz');       // an item, by identifier
$tree->path('quiz');      // from the root down to it
$tree->warnings;          // what the reader set aside
```

Le manifeste est d'abord lu et vérifié par `ScormManifestReader`, dans les mêmes `PackageLimits`. Chaque `Activity`
contient son identifiant, son titre, sa ressource, sa visibilité, ses enfants, son `Sequencing`, et pour SCORM 1.2 ses
`Prerequisites`.

### Ce qui est lu pour SCORM 2004

Le `<imsss:sequencing>` de l'organisation et de chaque élément. Quand il nomme une entrée de
`<imsss:sequencingCollection>` par `IDRef`, l'entrée donne chaque élément que l'élément ne déclare pas.

| Élément | Valeur |
|---|---|
| `<imsss:controlMode>` | `ControlMode` : `choice`, `choiceExit`, `flow`, `forwardOnly`, `useCurrentAttemptObjectiveInfo`, `useCurrentAttemptProgressInfo` |
| `<imsss:sequencingRules>` | Des listes de `SequencingRule` par `RuleTiming` — règles de précondition, de sortie et de postcondition —, chacune avec sa liste de `RuleCondition`, `all` ou `any`, et son `RuleAction` |
| `<imsss:limitConditions>` | `LimitConditions` : `attemptLimit` — `0` se lit comme aucune limite — et `attemptAbsoluteDurationLimit` |
| `<imsss:rollupRules>` | Une liste de `RollupRule`, et dans `RollupControls` les `rollupObjectiveSatisfied`, `rollupProgressCompletion` et `objectiveMeasureWeight` |
| `<adlseq:rollupConsiderations>` | Dans `RollupControls` : `requiredForSatisfied`, `requiredForNotSatisfied`, `requiredForCompleted`, `requiredForIncomplete` (`Requirement`) et `measureSatisfactionIfActive` |
| `<imsss:objectives>` | Une liste d'`Objective`, le primaire en premier : `objectiveID`, `satisfiedByMeasure`, `minNormalizedMeasure`, et chaque `<imsss:mapInfo>` comme un `ObjectiveMap` vers un objectif global |
| `<imsss:randomizationControls>` | `RandomizationControls` : `selectionTiming`, `selectCount`, `randomizationTiming`, `reorderChildren` |
| `<imsss:deliveryControls>` | `DeliveryControls` : `tracked`, `completionSetByContent`, `objectiveSetByContent` |

Une activité sans séquencement a les valeurs par défaut d'IMS Simple Sequencing : choix ouvert, enchaînement fermé. Une
valeur que le lecteur ne peut pas utiliser — un booléen qui n'en est pas un, un nombre hors de son intervalle, un moment
inconnu — est notée et sa valeur par défaut conservée. Une règle dont l'action est inconnue, dont l'action est d'un autre
moment, dont la condition est inconnue ou qui n'a aucune condition est notée et laissée de côté. Une activité peut
déclarer au plus 32 règles d'un même moment et 32 règles de cumul, 16 conditions par règle, 64 objectifs et 16
correspondances par objectif ; au-delà, le manifeste est refusé.

### Ce qui est lu pour SCORM 1.2

Le `<adlcp:prerequisites>` de chaque élément, de type `aicc_script` ou sans type, analysé par `Prerequisites::parse()` :

| Écriture | Satisfaite quand |
|---|---|
| `intro` | L'élément `intro` est réussi ou achevé |
| `a & b`, `a \| b` | Les deux, l'un ou l'autre |
| `~a` | `a` n'est pas satisfaite |
| `(a \| b) & c` | Les parenthèses groupent ; `&` lie avant `\|` |
| `quiz = "failed"`, `quiz <> passed` | Le statut comparé : `passed`, `completed`, `failed`, `incomplete`, `browsed`, `not attempted`, ou leurs initiales `p`, `c`, `f`, `i`, `b`, `n` |
| `2*{a, b, c}` | Au moins deux des membres sont satisfaits |

Un élément que l'arbre ne contient pas se lit comme non tenté. Une expression fait au plus 4 096 octets et 32 niveaux
d'imbrication ; une expression qui ne peut pas être lue est notée et laissée de côté. Un arbre SCORM 1.2 ouvre
l'enchaînement et le choix sur chaque grappe, et laisse le contenu seul fixer l'achèvement et la réussite.

## Exécuter une session

```php
use Phil\ElearningPackages\Sequencing\AttemptResult;
use Phil\ElearningPackages\Sequencing\NavigationRequest;
use Phil\ElearningPackages\Sequencing\Outcome;
use Phil\ElearningPackages\Sequencing\Sequencer;
use Phil\ElearningPackages\Sequencing\SequencingState;

$sequencer = new Sequencer();
$state = SequencingState::begin();

$result = $sequencer->navigate($tree, $state, NavigationRequest::Start);
$state = $result->state;           // always the state to keep
$result->outcome;                  // Outcome::Delivered
$result->activity;                 // 'intro': launch its resource

// The content reports while it runs, from the run-time data:
$state = $sequencer->report($tree, $state, AttemptResult::fromRuntime($data->status(), $sessionTime));

$result = $sequencer->navigate($tree, $state, NavigationRequest::Continue);
$result = $sequencer->navigate($tree, $result->state, NavigationRequest::Choice, 'quiz');
```

| Demande | Ce qu'elle fait |
|---|---|
| `Start` | Commence une session : enchaîne depuis la racine jusqu'à sa première feuille livrable. Refusée tant qu'une session est en cours ou suspendue |
| `ResumeAll` | Livre l'activité qu'un `SuspendAll` a suspendue, ses tentatives reprises plutôt que recommencées |
| `Continue`, `Previous` | Termine la tentative en cours, puis enchaîne vers la feuille suivante ou la précédente. Exige l'enchaînement sur le parent ; `Previous` est refusée sous « en avant seulement ». `Continue` après la dernière activité termine la session |
| `Choice` | Termine la tentative en cours et livre l'activité nommée — une grappe est entrée par enchaînement. Refusée là où le parent ferme le choix, là où une règle de masquage du choix s'applique sur le chemin, là où une activité en cours ne peut pas être quittée (`choiceExit`), vers l'arrière sous « en avant seulement », au-delà d'une règle d'arrêt du parcours vers l'avant, ou pour un enfant non tiré dans cette tentative |
| `Exit` | Termine la tentative en cours. L'issue est `Waiting` jusqu'à ce que l'apprenant choisisse, sauf si la terminaison atteint la racine, ce qui termine la session |
| `ExitAll` | Termine chaque tentative en cours et la session |
| `SuspendAll` | Suspend l'activité courante et ses ancêtres et termine la session ; la session suivante commence par `ResumeAll` |

| `Outcome` | Sens |
|---|---|
| `Delivered` | `SequencingResult::$activity` est la feuille à lancer |
| `SessionEnded` | La session est terminée : affichez la page propre à la plateforme |
| `Waiting` | Rien à lancer tant que l'apprenant ne fait pas de choix |
| `Refused` | `SequencingResult::$reason` dit pourquoi, en une phrase. Un refus venu après la fin de la tentative en cours renvoie quand même cet état |

### Ce que le moteur applique

- **Terminaison.** Terminer une tentative sur une feuille suivie l'achève et satisfait son objectif primaire quand son
  contenu n'a fixé ni l'un ni l'autre, sauf si `completionSetByContent` ou `objectiveSetByContent` dit que le contenu
  seul les fixe. Une feuille dont le contenu a demandé de suspendre (`AttemptResult::$suspend`, `cmi.exit` fixé à
  `suspend`) reste suspendue : sa prochaine livraison la reprend. Viennent ensuite les règles de sortie des ancêtres, de
  la racine vers le bas ; la première qui s'applique termine les tentatives en dessous et sur elle. Puis les règles de
  postcondition de l'activité courante : `exitParent` remonte et termine la tentative du parent, `exitAll` termine la
  session, `retry`, `retryAll`, `continue` et `previous` remplacent la demande. Une terminaison qui atteint la racine
  termine la session.
- **Règles de précondition.** `skip` est sautée par l'enchaînement, dans les deux sens, et reste ouverte au choix ;
  `disabled` arrête la livraison et l'enchaînement ; `hiddenFromChoice` refuse un choix sur l'activité et en dessous ;
  `stopForwardTraversal` refuse un choix qui avance au-delà.
- **Limites.** Une activité dont les tentatives sont épuisées n'est plus livrée. Une durée rapportée au-delà de
  `attemptAbsoluteDurationLimit` rend `timeLimitExceeded` vrai.
- **Livraison.** Chaque activité sur le chemin de la racine à la feuille livrée qui n'est pas en cours commence une
  nouvelle tentative — son achèvement, ses objectifs et sa durée effacés —, ou reprend quand elle est suspendue. Une
  nouvelle tentative sur une grappe efface aussi les objectifs de ses descendants sous `useCurrentAttemptObjectiveInfo`,
  et leur achèvement sous `useCurrentAttemptProgressInfo`. L'arbre entier est une seule tentative : celle de la racine,
  de `Start` à la fin de la session.
- **Cumul.** Après chaque rapport et chaque fin de tentative, de l'activité jusqu'à la racine : la mesure d'une grappe
  est la moyenne des mesures de ses enfants suivis pondérée par `objectiveMeasureWeight`, sur l'ensemble d'entre eux ; un
  objectif primaire satisfait par la mesure est satisfait à `minNormalizedMeasure` ou au-dessus, et attend tant qu'il est
  actif quand `measureSatisfactionIfActive` est faux ; sinon les règles de cumul décident — non satisfait puis satisfait,
  incomplet puis achevé —, ou les valeurs par défaut : satisfait quand tous les enfants le sont, non satisfait quand la
  satisfaction de tous est connue ; achevé quand tous le sont, incomplet quand l'achèvement de tous est connu. Un enfant
  compte quand il est suivi, non désactivé, que son `rollupObjectiveSatisfied` ou son `rollupProgressCompletion` le
  permet, et que ses considérations de cumul s'appliquent. Une condition de cumul sur un statut inconnu est inconnue, et
  une règle ne s'applique que sur des réponses connues ; une règle de séquencement lit un statut inconnu comme faux,
  avant `not`.
- **Objectifs globaux.** Un objectif lit la satisfaction et la mesure du premier objectif global avec lequel il est
  en correspondance par `readSatisfiedStatus` ou `readNormalizedMeasure` qui les connaît, sinon les siennes ; chaque
  changement des siennes est écrit dans les objectifs globaux avec lesquels il est en correspondance par
  `writeSatisfiedStatus` ou `writeNormalizedMeasure`.
- **Sélection et réordonnancement.** Les enfants d'une grappe sont tirés et mélangés quand l'enchaînement ou un choix en
  a d'abord besoin, à partir du germe de l'état, de la grappe et de son nombre de tentatives : le même état donne le même
  tirage. Ils sont conservés dans l'état pour la session (`once`) ou tirés de nouveau pour la tentative suivante
  (`onEachNewAttempt`).
- **SCORM 1.2.** Un élément dont les prérequis ne sont pas satisfaits est sauté par l'enchaînement et refusé au choix. Le
  statut d'un élément est le `lesson_status` que son contenu a rapporté en dernier, sinon ce que ses données indiquent —
  `passed` ou `failed` d'après sa satisfaction, `completed` ou `incomplete` d'après son achèvement, `not attempted` — ;
  `Sequencer::lessonStatus()` le donne.

### Rapporter des résultats

`report()` enregistre ce que le contenu de l'activité livrée a rapporté, puis le cumule ; elle lève `SequencingRefused`
quand aucune tentative n'est en cours.

| `AttemptResult` | Fixe |
|---|---|
| `$completed` | Son achèvement |
| `$passed` | La satisfaction de son objectif primaire |
| `$scaledScore` | La mesure de son objectif primaire, conservée de -1 à 1 |
| `$objectives` | La satisfaction et la mesure de ses autres objectifs, par identifiant, sous forme d'`ObjectiveStatus` ; un identifiant qu'elle ne déclare pas est laissé de côté |
| `$duration` | La durée de sa tentative, pour `timeLimitExceeded` |
| `$lessonStatus` | Le `lesson_status` de SCORM 1.2, pour les prérequis |
| `$suspend` | Si la tentative est suspendue quand elle se termine |

`AttemptResult::fromRuntime()` lit un `Runtime\RuntimeStatus` ; `AttemptResult::fromLessonStatus()` lit un `lesson_status`
de SCORM 1.2, `browsed` conservé tel qu'écrit. Une valeur nulle laisse ce que l'état contient.

## L'état que la plateforme conserve

`SequencingState` est tout ce que le séquenceur sait d'un apprenant sur un arbre. Une plateforme le stocke après chaque
demande, et le rend avec la suivante :

```php
$stored = json_encode($state->toArray(), JSON_THROW_ON_ERROR);
// …
$state = SequencingState::fromArray(json_decode($stored, true, flags: JSON_THROW_ON_ERROR), $tree);
```

| Clé | Contient |
|---|---|
| `version` | `1`, la version de cette forme |
| `seed` | Le germe de la sélection et du réordonnancement des enfants, tiré par `begin()` ou qu'on lui donne |
| `current` | L'activité livrée ou livrée en dernier, nulle hors d'une session |
| `suspended` | L'activité qu'a suspendue `SuspendAll`, nulle sinon |
| `activities` | Par identifiant, dans l'ordre de l'arbre, seulement celles dont on sait quelque chose : `attempts`, `active`, `suspended`, `completed` (nul tant qu'inconnu), `duration` en centièmes de seconde, `lesson`, `objectives` par clé — l'identifiant, ou `#primary` pour un objectif primaire sans identifiant — chacun avec `satisfied` et `measure`, et `order`, les enfants tirés |
| `globals` | Les objectifs globaux par identifiant, chacun avec `satisfied` et `measure` |

`fromArray()` vérifie chaque valeur et que chaque identifiant est de l'arbre, et lève `SequencingRefused` sinon : un état
stocké pour une autre organisation, ou modifié à la main, est refusé plutôt que cru. Un état appartient à un seul
apprenant et à un seul arbre ; les objectifs globaux sont conservés par état, de sorte qu'une plateforme qui les partage
entre des cours — comme le demande `objectivesGlobalToSystem` — les copie elle-même d'un état à l'autre. Lire `activity()`
donne un `ActivityStatus`, `global()` et `globals()` donnent des valeurs `ObjectiveStatus`. L'état se lit de la même façon
dans n'importe quel *worker* : le `Sequencer` et l'arbre sont immuables et ne gardent rien entre deux demandes.

## Ce qui n'est pas pris en charge

- Les demandes de navigation `Abandon`, `AbandonAll` et `Jump`.
- `adlseq:constrainedChoiceConsiderations` (`preventActivation`, `constrainChoice`), `adlseq:objectives` et les
  correspondances d'achèvement et de progression de SCORM 2004 4e édition, et le cumul de l'achèvement par la mesure
  (`completedByMeasure`, `minProgressMeasure`, `progressWeight`).
- `outsideAvailableTimeRange`, toujours faux : les heures de début et de fin ne sont pas lues. Les durées de tentative et
  d'activité autres que `attemptAbsoluteDurationLimit`, et `adlnav:presentation`.
- La limite de tentatives ne retire pas un enfant du cumul de son parent.
- L'effacement des données des descendants sous `useCurrentAttemptObjectiveInfo` et `useCurrentAttemptProgressInfo` a lieu
  quand la nouvelle tentative de la grappe commence, plutôt qu'en suivant la tentative à laquelle chaque valeur appartient.
- Une table des matières : une plateforme liste les activités de l'arbre et interroge le séquenceur sur un choix ; l'état
  est immuable, de sorte qu'un `navigate()` dont le résultat n'est pas conservé ne change rien.

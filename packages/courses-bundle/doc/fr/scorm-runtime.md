<!-- phil-dev source-sha256=b7b79b000e4d359d7b28b2eb2cba059a6fab3d6aeb78f94e24b2c072f616e35c -->
# Environnement d'exécution SCORM

Une activité SCORM s'exécute dans la plateforme : son contenu trouve l'API d'exécution de sa version, signale où en est
l'apprenant, et la plateforme conserve ce qu'il signale comme les données de suivi d'une tentative. Le contenu SCORM 1.2
trouve `API` et appelle `LMSInitialize()`, `LMSGetValue()`, `LMSSetValue()`, `LMSCommit()` et `LMSFinish()` ; le contenu
SCORM 2004 trouve `API_1484_11` et appelle `Initialize()`, `GetValue()`, `SetValue()`, `Commit()` et `Terminate()`. Le
modèle de données est celui de phil/elearning-packages, qui vérifie chaque valeur dans le navigateur, puis de nouveau
sur le serveur.

L'environnement d'exécution est activé par défaut. Avec `phil_courses.scorm.enabled: false`, une activité SCORM est
encadrée sans API et l'apprenant la marque terminée.

## Le déroulement d'une session

```text
activity page (application origin)            frame (content origin)              SCO (content origin)
  Stimulus controller scorm-runtime   <-- postMessage -->   API / API_1484_11   <-- window.parent --  content
  POST /courses/scorm/commit, sendBeacon
```

1. L'apprenant lance l'activité. Le bundle ouvre une session d'une tentative et produit la page de l'activité, qui
   contient le contrôleur Stimulus `phil--courses-bundle--scorm-runtime`, le jeton de la tentative et l'état de la
   session : les données de suivi avec lesquelles elle commence et le modèle de données.
2. Le contrôleur encadre `<content base URL>/_phil/scorm/frame.html`, le cadre de l'environnement d'exécution, servi sur
   l'origine du contenu. Quand le cadre dit qu'il est prêt, le contrôleur lui remet l'état — par `postMessage()`, à
   l'origine du contenu seulement.
3. Le cadre place l'API de la version sur sa fenêtre, puis encadre le SCO, sur la même origine : le SCO trouve l'API
   dans sa fenêtre parente, comme SCORM le demande. Le cadre n'accepte que les messages de l'origine de l'application,
   que nomme `phil_courses.content.application_origin`, et seule l'origine de l'application peut l'encadrer.
4. Chaque appel reçoit tout de suite une réponse tirée des valeurs que conserve le cadre, avec le code d'erreur de la
   version. Chaque valeur que le contenu écrit est remise au contrôleur ; `LMSCommit()` ou `Commit()` les envoie au point
   de terminaison de validation, dans l'ordre où elles ont été écrites ; `LMSFinish()` ou `Terminate()` envoie le reste
   et met fin à la session.
5. Quand l'apprenant quitte la page sans que le contenu mette fin à la session, le contrôleur envoie ce qui reste avec
   `navigator.sendBeacon()`, ce qui met aussi fin à la session.
6. Chaque réponse du point de terminaison apporte le jeton de la validation suivante. Une session tranquille envoie une
   validation vide toutes les dix minutes, pour garder son jeton vivant.

La page dit, dans une région dynamique, si la progression est enregistrée, si le contenu a envoyé des valeurs que la
plateforme n'a pas conservées, quand la session a expiré ou est terminée, quand le temps alloué est écoulé, et qu'une tentative ouverte en révision ne
conserve rien.

## Le point de terminaison de validation

`POST /courses/scorm/commit`, sous le préfixe de `config/routes.php`, prend un objet JSON :

| Clé | Contient |
|---|---|
| `token` | Le jeton de tentative que la page a reçu, ou que la dernière réponse a donné |
| `csrf` | Le jeton CSRF sans état des formulaires des cours |
| `values` | Les éléments que le contenu a écrits depuis la dernière validation, dans l'ordre où il les a écrits, chacun avec sa valeur sous forme de texte ou de nombre |
| `finish` | Si la session prend fin |

| Réponse | Quand |
|---|---|
| `200` avec `errors`, `token`, `ended`, `next`, `exit`, `remaining`, `time_up` | La validation est conservée : `errors` nomme chaque élément laissé de côté avec son code d'erreur ; `token` est le jeton de la validation suivante, null une fois la session terminée ; `next` est l'activité où mène une demande de navigation, `exit` indique si elle mène au plan ; sous une limite de temps, `remaining` est le nombre de secondes restant avant l'échéance, et `time_up` dit que le temps a mis fin à la tentative — voir [Les limites de temps](#les-limites-de-temps) |
| `400` | Le corps n'est pas un tel objet |
| `403` | Le jeton CSRF ou le jeton de tentative est refusé : inconnu, falsifié, expiré, ou celui d'un autre apprenant |
| `409` | La session est terminée : elle a pris fin, ou un lancement plus récent en a ouvert une nouvelle ; ou sa tentative a pris fin, et ne s'ouvre plus qu'en révision |
| `413` | Le corps dépasse 2 Mio |

Le jeton de tentative nomme la tentative, la session et le moment où il expire, signés par un HMAC sous
`phil_courses.scorm.secret` ; il tient `phil_courses.scorm.token_ttl` secondes après la dernière validation, quatre
heures par défaut. Quand un apprenant est connecté, la tentative doit être la sienne ; un signal envoyé par balise au
moment où l'apprenant se déconnecte est tout de même accepté, sur son jeton seul.

## Les tentatives

Une tentative conserve les données de suivi d'un apprenant à une activité, d'une session à l'autre.

| La dernière tentative | Le lancement suivant |
|---|---|
| Aucune | Une nouvelle tentative, `cmi.entry` vaut `ab-initio` |
| Suspendue : le contenu a mis `cmi.core.exit` ou `cmi.exit` à `suspend` | La même tentative reprise, `cmi.entry` vaut `resume`, ses données conservées |
| SCORM 1.2, terminée sans être complétée, réussie ni échouée, et non suspendue | La même tentative, `cmi.entry` est vide, ses données conservées |
| Terminée : toute autre fin de session de SCORM 2004 ; un statut `completed`, `passed` ou `failed` en SCORM 1.2 | Une nouvelle tentative, `ab-initio`, ses données vides |
| Une session encore en cours — une page fermée avant de pouvoir le dire | Cette session prend fin d'abord, puis les règles ci-dessus s'appliquent |

Chaque lancement ouvre la session suivante de sa tentative et lui donne un nouveau jeton : la page d'un lancement
précédent ne valide plus rien, et reçoit `409`.

Quand une session commence, la plateforme fixe l'identifiant de l'apprenant — jamais un nom ni une adresse courriel —,
le crédit, le mode `normal`, l'entrée, et ce que le manifeste dit de l'élément : les données de lancement, la note de
maîtrise de SCORM 1.2 (`cmi.student_data.mastery_score`, sur cent) ou la note de passage normalisée de SCORM 2004
(`cmi.scaled_passing_score`, tirée de l'objectif principal satisfait par une mesure), le seuil d'achèvement de SCORM
2004, le temps alloué et ce qui arrive une fois qu'il est écoulé — le plus court de celui du manifeste et de celui de
l'activité, voir [Les limites de temps](#les-limites-de-temps). La sortie et le temps de session de la session
précédente sont oubliés.

Quand une session prend fin, le temps de session s'ajoute au temps total de la tentative — `HHHH:MM:SS.SS` en SCORM
1.2, une durée de l'ISO 8601 en SCORM 2004. En SCORM 1.2, un statut que le contenu n'a jamais fixé devient `completed`,
et avec une note de maîtrise et une note brute en mode crédit, le statut devient `passed` ou `failed` selon la note. En
SCORM 2004, l'achèvement est évalué selon le seuil d'achèvement et la mesure de progression, et la réussite selon la
note de passage normalisée et la note normalisée, comme `GetValue()` les répond.

## La progression

La tentative alimente la progression de l'apprenant dans l'activité, comme pour tout autre type d'activité :

- dès que le contenu signale l'activité complétée ou réussie, une validation l'enregistre : `ActivityProgressed`, puis
  `ActivitySatisfied` et `CourseCompleted` quand l'exigence de l'activité et celle du cours sont remplies ;
- la fin d'une session enregistre le résultat complet : complétée, réussie ou échouée, et la note — la note normalisée
  de SCORM 2004, sinon la note brute entre son minimum et son maximum, sur cent en SCORM 1.2 quand ils ne sont pas
  fixés ;
- un échec compte une fois la tentative terminée, de sorte que chaque tentative échouée use un essai de
  `max_attempts` ; un apprenant à court d'essais demande une seconde chance, comme pour toute activité ; une tentative
  laissée incomplète n'use aucun essai ;
- une session sans crédit, ou en mode consultation ou révision, n'enregistre rien.

L'exigence d'une activité SCORM importée est `Passed` quand son élément a une note de maîtrise ou une note de passage
normalisée, `CompletedOrPassed` sinon. Un achèvement ou une réussite n'est jamais défait par une tentative ultérieure.

`ScormSessionEnded` est envoyé à la fin de chaque session, avec l'inscription, l'activité et la tentative par leurs
identifiants, et si la tentative a pris fin.

## Le mode révision

Une tentative terminée s'ouvre de nouveau en révision. Le plan affiche **Réviser la dernière tentative** à côté d'une
activité SCORM dont l'apprenant a une tentative terminée, une fois l'activité atteinte — ouverte, faite ou sans essais
restants ; le formulaire envoie un `POST /{course}/activities/{activity}/review`, avec le jeton CSRF des cours, et la
page de l'activité encadre le contenu comme pour un lancement, avec un badge qui dit qu'il s'agit d'une révision.

- Le contenu lit les données de suivi de la dernière tentative terminée de l'apprenant, avec le mode `review`
  (`cmi.core.lesson_mode` en SCORM 1.2, `cmi.mode` en SCORM 2004), le crédit `no-credit` et une entrée vide ; la sortie
  et le temps de session de sa dernière session sont oubliés, comme à tout lancement.
- Le contenu peut encore appeler `SetValue()` : l'environnement d'exécution répond comme dans toute session, et garde
  la valeur dans le cadre pour que le contenu la relise, mais la page n'envoie rien — aucune validation, aucune balise à
  la fin — et le dit dans sa région dynamique.
- Aucune session n'est ouverte, la tentative reste telle quelle, et la page reçoit un jeton vide. Sur le serveur, une
  validation d'une tentative terminée est refusée avec `409`, quel que soit le jeton qu'elle porte : ni les données de
  suivi ni la progression ne changent.
- Une révision n'enregistre rien, n'envoie aucun événement et n'utilise aucun essai.

`ScormRuntime::review()` ouvre la révision, et `ScormRuntime::reviewable()` donne les activités d'une inscription qui ont
une tentative terminée. Les valeurs que la révision remet au contenu, et ce que répondent les appels du contenu, sont
tenues par les vecteurs de `tests/js/fixture/review.json`, que le serveur et le navigateur exécutent tous deux.

## Les limites de temps

Une tentative a droit au temps du manifeste — `adlcp:maxtimeallowed` en SCORM 1.2, l'`attemptAbsoluteDurationLimit` de
SCORM 2004 — avec son action, `time_limit_action`, et à la limite propre de l'activité, son réglage `time_limit` en
secondes, de 1 à 35 999 999 :

| Les limites | Le contenu reçoit | La plateforme met fin à la tentative |
|---|---|---|
| Celle du manifeste seulement, son action `continue,…` ou aucune | Ce temps et cette action | Jamais : le contenu applique son action |
| Celle du manifeste seulement, son action `exit,…` | Ce temps et cette action | À ce moment |
| Celle de l'activité seulement, ou plus courte que celle du manifeste, ou aussi longue | Le temps de l'activité, et l'action du manifeste quand elle sort, sinon `exit,message` | Au temps de l'activité |
| Celle du manifeste plus courte que celle de l'activité | Le temps et l'action du manifeste | Au temps du manifeste quand son action sort, sinon au temps de l'activité |

La plateforme compte le temps par sa propre horloge, quoi que le contenu rapporte dans son temps de session : les
secondes qu'a duré chaque session, de son lancement à sa fin. Le temps entre deux sessions n'est pas compté, et une
session fermée sans un mot — la page fermée avant de pouvoir le dire — compte jusqu'à sa dernière validation. La page
valide quand l'échéance arrive, et chaque réponse apporte les secondes restantes.

- Une validation qui atteint l'échéance est conservée, puis met fin à la session avec la sortie `time-out`, et à la
  tentative avec elle, même quand le contenu a demandé de suspendre : la réponse dit `time_up`.
- La validation d'une page en retard de plus de 30 secondes, `ScormRuntime::GRACE`, n'est pas conservée : la session
  prend fin avec les données des validations précédentes, et la réponse dit `time_up`.
- Une session laissée en cours après son échéance prend fin au lancement suivant, avec la sortie `time-out` ; une
  tentative suspendue après celle-ci — quand la limite a été abaissée — prend fin elle aussi au lancement suivant.
- Une tentative terminée par son temps, ni complétée ni réussie, compte pour échouée : elle utilise un essai de
  `max_attempts`.

Le temps qu'a duré une session est conservé avec sa tentative, dans les colonnes `elapsed` et `session_opened_at` de
`phil_course_scorm_attempt`, que `CourseTables::upgrade()` ajoute à une table antérieure.

## Les paquets de plusieurs SCO

Les SCO d'un paquet sont les activités du cours, dans l'ordre de l'organisation que l'import a lue : le plan est le
choix de l'apprenant pour la suite. Un SCO de SCORM 2004 peut aussi demander où aller quand sa session prend fin :

| `adl.nav.request` | La page mène à |
|---|---|
| `continue`, `previous` | L'activité SCORM suivante ou précédente du cours, sinon le plan |
| `{target=<item>}choice`, `{target=<item>}jump` | L'activité importée de cet élément, sinon le plan |
| `exit`, `exitAll`, `suspendAll`, `abandon`, `abandonAll` | Le plan |

`adl.nav.request_valid.continue` et `adl.nav.request_valid.previous` disent s'il y a une activité SCORM après celle-ci ou
avant elle ; `adl.nav.request_valid.choice` et `adl.nav.request_valid.jump` répondent `unknown`.

## Ce qui est pris en charge

| Partie | État |
|---|---|
| L'API de SCORM 1.2 (`API`) et de SCORM 2004 4e édition (`API_1484_11`), avec leurs états et leurs codes d'erreur | Pris en charge |
| Chaque élément du modèle de données de SCORM 1.2 et de SCORM 2004, leurs types, vocabulaires, intervalles, règles de lecture et d'écriture, `_children`, `_count` et `_version` | Pris en charge, vérifié dans le navigateur et sur le serveur |
| Objectifs, interactions, leurs objectifs et bonnes réponses, commentaires de SCORM 2004, chaque collection dans son plus petit maximum permis | Pris en charge |
| Les réponses des interactions de SCORM 2004 vérifiées selon le type de leur interaction | `true-false`, `choice`, `sequencing`, `likert`, `matching` et `numeric` ; `fill-in`, `long-fill-in`, `performance` et `other` bornées en longueur |
| `suspend_data` : 4 096 caractères en SCORM 1.2, 64 000 en SCORM 2004 | Pris en charge ; les données d'une tentative sont bornées à 1 Mio en tout |
| Temps de session ajouté au temps total ; règles de statut et de note de chaque version | Pris en charge |
| Tentatives : reprise après une suspension, nouvelle tentative après une tentative terminée, essais de l'activité | Pris en charge |
| Note de maîtrise, note de passage normalisée, seuil d'achèvement, données de lancement, temps alloué et son action, tirés du manifeste | Remis au contenu |
| Le mode révision d'une tentative terminée | Pris en charge : lecture seule, rien de conservé, validations refusées sur le serveur |
| Le temps alloué, tiré du manifeste ou de l'activité | Appliqué par la plateforme, par sa propre horloge |
| Demandes de navigation `continue`, `previous`, `choice`, `jump` et les sorties | Pris en charge, comme ci-dessus |
| Les données de suivi dans les demandes d'accès et d'effacement de phil/privacy-bundle | Pris en charge quand ce bundle est installé |

## Ce que le bundle ne fait pas encore

Le séquencement et la navigation complets de SCORM 2004 forment une fonctionnalité à part, prévue hors de l'environnement
d'exécution : d'ici là, chaque SCO est une activité du cours.

| Partie | Ce qui arrive à la place |
|---|---|
| Le séquencement et la navigation de SCORM 2004 — règles de séquencement, cumul, conditions limites, objectifs partagés entre des SCO, l'arbre des activités comme une seule tentative | Conservés avec l'activité à titre d'information ; chaque SCO est une activité du cours, et l'ordre et les exigences propres au cours s'appliquent |
| Les préalables de SCORM 1.2 | Conservés à titre d'information ; imposez plutôt l'ordre du cours |
| `adl.nav.request_valid.choice` et `.jump` pour une cible | Répondent `unknown` |
| Les formats des réponses `fill-in`, `long-fill-in`, `performance` et `other` | Bornés en longueur, non analysés |
| Deux validations d'une même session envoyées en même temps par deux onglets | La dernière conservée l'emporte |

## Vie privée

Les données de suivi appartiennent à l'apprenant : ses réponses aux interactions, ses commentaires, ce qu'il a écrit
dans un formulaire du contenu. Avec phil/privacy-bundle installé, `ScormPrivacy` donne les tentatives d'un apprenant —
ce que la plateforme a lu de chacune et ses données de suivi — dans une demande d'accès, et les supprime dans une demande
d'effacement ; l'apprenant est l'identifiant stable sous lequel les cours le connaissent. L'exportation contient le temps que la plateforme a mesuré et la sortie `time-out` d'une tentative terminée par son
temps. L'événement `ScormSessionEnded` ne porte que des identifiants. Les enregistrements cmi5 d'un apprenant ont leur
propre module de vie privée : voir [cmi5](cmi5.md#vie-privée).

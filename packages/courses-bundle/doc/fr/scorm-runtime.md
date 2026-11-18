<!-- phil-dev source-sha256=b9d1b6374cbe1186dd5394d8cca548a4125f209b46b2a4578b9d735ba4c74d9a -->
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
plateforme n'a pas conservées, et quand la session a expiré ou est terminée.

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
| `200` avec `errors`, `token`, `ended`, `next`, `exit` | La validation est conservée : `errors` nomme chaque élément laissé de côté avec son code d'erreur ; `token` est le jeton de la validation suivante, null une fois la session terminée ; `next` est l'activité où mène une demande de navigation, `exit` indique si elle mène au plan |
| `400` | Le corps n'est pas un tel objet |
| `403` | Le jeton CSRF ou le jeton de tentative est refusé : inconnu, falsifié, expiré, ou celui d'un autre apprenant |
| `409` | La session est terminée : elle a pris fin, ou un lancement plus récent en a ouvert une nouvelle |
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
2004, le temps alloué et ce qui arrive une fois qu'il est écoulé. La sortie et le temps de session de la session
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
| Demandes de navigation `continue`, `previous`, `choice`, `jump` et les sorties | Pris en charge, comme ci-dessus |
| Les données de suivi dans les demandes d'accès et d'effacement de phil/privacy-bundle | Pris en charge quand ce bundle est installé |

## Ce que le bundle ne fait pas encore

| Partie | Ce qui arrive à la place |
|---|---|
| Le séquencement et la navigation de SCORM 2004 — règles de séquencement, cumul, conditions limites, objectifs partagés entre des SCO, l'arbre des activités comme une seule tentative | Conservés avec l'activité à titre d'information ; chaque SCO est une activité du cours, et l'ordre et les exigences propres au cours s'appliquent |
| Les préalables de SCORM 1.2 | Conservés à titre d'information ; imposez plutôt l'ordre du cours |
| `adl.nav.request_valid.choice` et `.jump` pour une cible | Répondent `unknown` |
| Le mode révision d'une tentative terminée | Un lancement après une tentative terminée en commence une nouvelle |
| Le temps alloué appliqué par la plateforme | Remis au contenu, qui applique son action |
| Les formats des réponses `fill-in`, `long-fill-in`, `performance` et `other` | Bornés en longueur, non analysés |
| Deux validations d'une même session envoyées en même temps par deux onglets | La dernière conservée l'emporte |

## Vie privée

Les données de suivi appartiennent à l'apprenant : ses réponses aux interactions, ses commentaires, ce qu'il a écrit
dans un formulaire du contenu. Avec phil/privacy-bundle installé, `ScormPrivacy` donne les tentatives d'un apprenant —
ce que la plateforme a lu de chacune et ses données de suivi — dans une demande d'accès, et les supprime dans une demande
d'effacement ; l'apprenant est l'identifiant stable sous lequel les cours le connaissent. L'événement
`ScormSessionEnded` ne porte que des identifiants.

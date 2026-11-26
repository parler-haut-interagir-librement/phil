<!-- phil-dev source-sha256=b0e81a93cd23427d0762c155e30cb22d87046807b795e5a57670df229c378a3f -->
# cmi5

Le bundle importe des cours cmi5, lance leurs unités assignables comme l'exige cmi5, et reçoit leurs énoncés par un point
de terminaison xAPI qui les vérifie selon les règles de cmi5.

## Import d'un cours cmi5

Un paquet ZIP contenant `cmi5.xml` à sa racine, ou une structure de cours seule dont les unités ont des URL absolues,
s'importe avec la même commande que SCORM :

```sh
php bin/console phil:courses:import var/import/navigation.zip
php bin/console phil:courses:import var/import/cmi5.xml
```

| Dans la structure de cours | Dans le cours |
|---|---|
| Le cours, son titre et sa description dans `phil_courses.import.language`, sinon le premier écrit | Le cours |
| Un bloc, à toute profondeur | Un `Block` : son titre, son identifiant comme source, son bloc parent, et sa place parmi les enfants de ce parent, blocs et unités comptés ensemble |
| Un bloc du premier niveau | Aussi un module du plan ; ses unités, et celles de ses blocs imbriqués, deviennent ses activités, les titres des blocs imbriqués en préfixe |
| Une unité du premier niveau | Une activité d'un module nommé d'après le cours, dans aucun bloc |
| Une unité | Une activité `cmi5` : son IRI comme `au_id` et source, son `moveOn` comme exigence, son `masteryScore`, `launchMethod`, `activityType`, `launchParameters` et `entitlementKey`, et le bloc le plus interne qui la contient |
| Une unité dont le `moveOn` est `NotApplicable` | Une activité que le cours n'exige pas, car cmi5 la compte satisfaite |
| L'URL d'une unité | Un fichier du paquet, servi depuis l'origine du contenu, ou une adresse absolue |

Les identifiants sont uniques, `moveOn` et `launchMethod` sont des valeurs que cmi5 définit, un score de maîtrise est un
nombre décimal de 0 à 1 ; une structure qui enfreint l'une de ces règles est refusée. Les objectifs sont laissés de
côté, et le rapport le dit. La clé d'autorisation est remise à l'unité, non vérifiée. La commande affiche combien de
modules, de blocs, d'activités et de fichiers le cours contient.

`Courses::blocks()` donne les blocs d'un cours, chaque parent avant les blocs qu'il contient, les frères et sœurs dans
leur ordre ; l'arbre se reconstruit à partir de leurs parents et de leurs positions, et du `block` de chaque activité.
L'import d'un paquet borne le nombre de blocs d'un cours par `phil_courses.import.limits.items`.

## Lancement d'une unité

Quand l'apprenant lance une activité cmi5, le bundle :

1. abandonne la session encore ouverte pour cet apprenant et cette unité, s'il y en a une, et émet son énoncé
   `abandoned` ;
2. ouvre une session, dont le jeton est valide pendant `phil_courses.cmi5.session_ttl` secondes ;
3. émet l'énoncé `launched`, avec le mode de lancement `Normal`, l'URL de lancement, le `moveOn` et les paramètres de
   lancement ;
4. envoie l'apprenant à l'URL de l'unité, avec les paramètres de lancement de cmi5 :

| Paramètre | Valeur |
|---|---|
| `endpoint` | Le point de terminaison xAPI du bundle, terminé par une barre oblique |
| `fetch` | L'URL de récupération de la session |
| `registration` | L'enregistrement de l'inscription |
| `activityId` | L'IRI de l'activité sur cette plateforme : la page d'accueil, puis `/courses/activities/` et l'identifiant de l'activité |
| `actor` | L'apprenant comme agent xAPI : un compte dont la page d'accueil est `phil_courses.cmi5.actor_home_page` et dont le nom est l'identifiant stable de l'apprenant |

Une unité qui demande `OwnWindow` s'ouvre dans sa propre fenêtre ; les autres sont encadrées dans la page de l'activité.
L'IRI de l'unité dans la structure de cours est l'identifiant de l'éditeur, dans le `grouping` du gabarit de contexte.

## L'URL de récupération

L'unité envoie une requête `POST` à l'URL de récupération et reçoit une seule fois `{"auth-token": "…"}`. Une deuxième
requête répond par l'erreur `1` ; une session inconnue, terminée ou expirée, par l'erreur `2` ; une requête qui n'est pas
un `POST`, par l'erreur `3`. Le jeton est l'identifiant de la session et un HMAC de celui-ci sous une
clé dérivée de `phil_courses.cmi5.secret` par HKDF-SHA256 pour cet usage seul (`Cmi5Tokens::USAGE`) : aucun secret n'est conservé dans la base de données. L'unité l'envoie comme identifiants
HTTP Basic avec chaque requête xAPI.

## Le point de terminaison xAPI

| Ressource | Méthodes | Ce que fait le bundle |
|---|---|---|
| `statements` | `POST`, `PUT` avec `statementId` | Vérifie chaque énoncé, le conserve par l'entrepôt de traces d'apprentissage, l'applique ; `GET` est refusé |
| `activities/state` avec `stateId=LMS.LaunchData` | `GET` | Les données de lancement : le gabarit de contexte, le mode de lancement, le `moveOn`, le score de maîtrise, les paramètres de lancement, la clé d'autorisation ; l'écriture en est refusée |
| `activities/state` avec un autre `stateId` | `GET`, `PUT`, `POST`, `DELETE` | Les propres documents d'état de l'unité, 64 Kio chacun, par l'entrepôt de traces d'apprentissage ; un `POST` fusionne un objet JSON dans celui qui est conservé, au premier niveau seulement, et répond `400` pour tout autre document |
| `agents/profile` | `GET` | Introuvable : la plateforme ne garde aucune préférence d'apprenant ; l'écriture en est refusée |
| `about` | `GET` | `{"version": ["1.0.3"]}` |

Chaque requête sauf `about` porte le jeton d'une session ouverte et l'en-tête `X-Experience-API-Version: 1.0.x`. Une
requête d'état nomme l'activité, l'agent et l'enregistrement de sa session. Les requêtes d'origine croisée reçoivent une
réponse pour l'origine du contenu et `phil_courses.cmi5.allowed_origins`, jamais pour une origine quelconque.

Les sessions des [activités xAPI](xapi-activities.md) parlent au même point de terminaison : leurs énoncés passent les
vérifications des activités xAPI au lieu des règles de cmi5, et leurs documents d'état appartiennent à l'identifiant
d'activité de leur paquet ; les données de lancement sont celles de cmi5 seulement.

## Les règles de cmi5

Un énoncé est accepté quand il respecte ces règles, et refusé avec `403` et la règle qu'il a enfreinte sinon :

- son acteur est l'agent du lancement, par son compte seulement ; son enregistrement et son extension `sessionid` sont
  ceux du lancement ; un `id`, quand il est donné, est un UUID ;
- un énoncé dont le verbe est défini par cmi5 a l'IRI de l'activité comme objet et la catégorie `cmi5` ;
- `launched`, `abandoned`, `waived` et `satisfied` sont à émettre par la plateforme ;
- `initialized` vient en premier et une seule fois ; rien ne suit `terminated` ; un énoncé d'un autre verbe vient entre
  les deux ;
- `completed`, `passed` et `failed` portent la catégorie `moveon` et une durée en ISO 8601, comme `terminated` ;
- `completed` fixe son achèvement à vrai, une fois par enregistrement ;
- `passed` et `failed` fixent leur succès ; rien d'eux ne suit un `passed` ; avec un score de maîtrise, ils donnent un
  score normalisé au moins égal, ou inférieur, à ce score ;
- un score normalisé est un nombre de -1 à 1.

Une requête contient 50 énoncés et 1 Mio au plus. Son corps — sa taille, son JSON, son nombre d'énoncés, chacun un
objet — est d'abord vérifié en entier. Ses énoncés sont ensuite vérifiés, conservés et appliqués l'un après l'autre,
chacun selon l'état que les précédents ont laissé : un énoncé refusé arrête la requête avec `403`, et les énoncés qui
le précèdent restent conservés et appliqués.

## Application des énoncés

`initialized` ouvre la session et `terminated` la termine. `completed`, `passed` et `failed` font avancer la progression
de l'apprenant, que l'exigence de l'activité — son `moveOn` — satisfait alors. Quand un enseignant dispense une
activité cmi5, la plateforme émet son énoncé `waived`, dans une session à part.

## Les énoncés satisfied

Chaque fois qu'une unité est satisfaite — par un énoncé ou par une dispense —, la plateforme émet un énoncé `satisfied`
pour chaque bloc que l'apprenant a satisfait et dont il n'a pas encore d'énoncé, puis pour le cours :

- un bloc est satisfait quand chaque activité obligatoire qu'il contient, en lui ou dans ses blocs imbriqués, est
  satisfaite ; une unité dispensée compte comme satisfaite, et une unité qui n'est pas obligatoire — un `moveOn` de
  `NotApplicable` — est laissée de côté ; un bloc qui ne contient aucune activité n'est jamais satisfait ;
- les blocs les plus profonds viennent en premier : un bloc imbriqué avant le bloc qui le contient ;
- le cours est satisfait quand chaque activité obligatoire du cours l'est ;
- l'objet de l'énoncé d'un bloc est l'identifiant que lui donne sa structure de cours ; celui du cours, l'identifiant de
  sa structure de cours, ou l'IRI du cours sur la plateforme — la page d'accueil, puis `/courses/` et son identifiant —
  pour un cours qui n'en a pas ; le cours est le `grouping` des deux, avec la catégorie `moveon` ;
- l'énoncé appartient à la session qui a satisfait l'unité — la session de la dispense pour une dispense —, avec son
  enregistrement et l'apprenant comme acteur.

Chaque énoncé est émis une seule fois pour une inscription et un bloc, ou pour le cours : la satisfaction est notée dans
`phil_course_satisfaction` avant que l'énoncé soit conservé, de sorte que deux requêtes simultanées l'émettent une seule
fois. Quand l'entrepôt de traces d'apprentissage refuse l'énoncé, la note est retirée et l'énoncé est émis de nouveau
par la prochaine unité satisfaite. L'énoncé de chaque bloc déclenche `BlockSatisfied`, avec les identifiants de
l'inscription, du bloc et de l'énoncé.

La satisfaction est revérifiée à chaque résultat d'une unité — `completed`, `passed` ou `failed`, d'une unité
obligatoire ou non — et à chaque dispense. Une inscription dont toutes les unités sont satisfaites mais qui n'a aucune
note dans `phil_course_satisfaction` — son énoncé refusé par l'entrepôt, ou ses tables mises à jour par
`CourseTables::upgrade()` après que ses unités ont été satisfaites — reçoit les énoncés de ses blocs et de son cours à
son prochain résultat, datés du moment où ils sont émis. Une inscription qui a terminé son cours sous un schéma sans
`phil_course_satisfaction` s'est vu émettre alors un énoncé du cours, nommé par l'IRI de la plateforme : elle en reçoit
un deuxième, nommé par la structure de cours.

### Les règles de cmi5 que suivent ces comportements

| Comportement | La règle de cmi5 |
|---|---|
| Une unité dont le `moveOn` est `NotApplicable` est importée comme une activité que le cours n'exige pas, et aucun énoncé n'est émis pour elle | Le `moveOn` d'une unité assignable dans la structure de cours : `NotApplicable` signifie que l'unité est considérée satisfaite, sans énoncé de sa part. Ne pas l'exiger laisse les blocs et le cours aux autres unités |
| Le cours est satisfait quand chaque unité obligatoire l'est, une unité dispensée comptée, et son énoncé est émis après une dispense comme après un énoncé | Le verbe `satisfied` : la plateforme l'émet pour un bloc ou un cours quand chaque unité qu'il contient a rempli ses critères `moveOn`. Une dispense est la façon qu'a la plateforme de les remplir : le verbe `waived` |
| L'objet de l'énoncé du cours est l'identifiant de sa structure de cours ; celui d'un bloc, l'identifiant du bloc dans la structure | Le verbe `satisfied` : son objet est le bloc ou le cours comme activité, identifié par l'`id` que lui donne la structure de cours. L'IRI du cours sur la plateforme ne nomme qu'un cours qu'aucune structure de cours cmi5 ne décrit |

Un énoncé `satisfied` émis en retard, pour un cours dont les unités ont été satisfaites avant, suit toujours ces règles :
le cours est satisfait, et l'énoncé dit quand la plateforme l'a noté.

## L'entrepôt de traces d'apprentissage

Les énoncés et les documents d'état passent par le port `LearningRecordStore`. Le bundle a vérifié chaque énoncé avant
que l'entrepôt le reçoive. Trois entrepôts s'y branchent :

| Entrepôt | Comment | Ce qu'il fait |
|---|---|---|
| L'entrepôt intégré au bundle | Par défaut | Conserve les énoncés et les documents d'état par DBAL ; un énoncé envoyé deux fois avec le même identifiant n'est conservé qu'une fois |
| Un entrepôt xAPI distant | `phil_courses.cmi5.remote_lrs`, avec phil/safe-http | Envoie les énoncés à l'entrepôt distant ; les documents d'état restent dans l'entrepôt intégré au bundle |
| Celui de l'application | Son service nommé dans `phil_courses.cmi5.lrs` | Ce que fait l'adaptateur ; voir [Points d'extension](extension-points.md) |

### Un entrepôt xAPI distant

```yaml
phil_courses:
    cmi5:
        remote_lrs:
            enabled: true
            endpoint: 'https://lrs.example.org/xapi/'
            username: '%env(LRS_KEY)%'
            password: '%env(LRS_SECRET)%'
```

`HttpLearningRecordStore` envoie chaque énoncé par `PUT` à `<endpoint>/statements?statementId=<id>`, en JSON, avec
l'en-tête `X-Experience-API-Version` de `version` et l'authentification HTTP Basic quand un nom d'utilisateur et un mot
de passe sont donnés. Il passe par un `SafeHttpClient` à lui, réinitialisé entre les requêtes :

- le point de terminaison est une adresse `https` d'un hôte public, sans requête ni fragment ; un hôte qui est ou qui se
  résout en une adresse de boucle locale, privée, de lien local ou réservée est refusé, comme toute redirection ;
- l'entrepôt peut rester silencieux `timeout` secondes, et un énoncé prend `max_duration` secondes au plus ;
- une réponse `2xx` conserve l'énoncé ; `409 Conflict` signifie que l'entrepôt l'a déjà, et compte comme conservé ;
- toute autre réponse, ou un entrepôt hors d'atteinte, lance `RecordStoreUnavailable`, qui ne nomme que le statut : le
  point de terminaison xAPI répond `503` à l'unité, pour qu'elle renvoie ses énoncés, et la page de lancement dit que le
  lancement n'a pas pu être enregistré ;
- le journal, quand l'application a un service `logger`, nomme l'identifiant de l'énoncé, le statut et le genre
  d'échec ; jamais l'énoncé, l'enregistrement, l'apprenant, les identifiants de connexion ni ce que l'entrepôt a
  répondu.

### Les documents d'état restent locaux

Avec un entrepôt distant, les documents d'état ne quittent jamais l'application : `HttpLearningRecordStore` les lit, les
écrit et les supprime dans l'entrepôt intégré au bundle, sur DBAL, qui lui est donné comme `DbalLearningRecordStore`. Ce
sont les données d'exécution des lancements — ce qu'une unité garde d'un lancement à l'autre —, relues par les unités
par le point de terminaison du bundle, jamais par les lecteurs de l'entrepôt distant. La ressource State de xAPI nomme
son document par un agent, que le port `LearningRecordStore` ne donne pas : une application qui a besoin des documents
d'état dans l'entrepôt distant écrit son propre adaptateur du port, nommé dans `phil_courses.cmi5.lrs`.

### Les limites de l'entrepôt distant

- **Réseaux privés.** phil/safe-http refuse un hôte qui est ou qui se résout en une adresse de boucle locale, privée, de
  lien local ou réservée. Un entrepôt de traces d'apprentissage sur le réseau même de l'application est hors d'atteinte
  de l'entrepôt distant : une application qui en exécute un là écrit son propre adaptateur du port.
- **Requêtes de plusieurs énoncés.** Un énoncé est conservé par l'entrepôt distant avant que le bundle l'applique, l'un
  après l'autre. Quand l'entrepôt n'en conserve pas un, la requête se termine par `503`, et les énoncés qui le précèdent
  restent conservés et appliqués. Quand l'unité renvoie toute la requête, les règles de cmi5 peuvent la refuser avec
  `403` — un `initialized` envoyé deux fois, par exemple — : les énoncés conservés ne sont pas perdus, mais l'unité doit
  envoyer le reste seul.
- **Effacement.** Un énoncé que conserve l'entrepôt distant ne peut pas être effacé par la plateforme : voir
  [Vie privée](#vie-privée).

## Vie privée

Avec phil/privacy-bundle installé, `Cmi5Privacy` donne les enregistrements cmi5 d'un apprenant dans une demande d'accès
et les efface dans une demande d'effacement, sous le module `courses_cmi5`. L'apprenant est l'identifiant stable sous
lequel les cours le connaissent ; ses données sont celles des enregistrements (registrations) de ses inscriptions, les sessions de lancement et les
énoncés de ses [activités xAPI](xapi-activities.md) compris.

| Enregistrement | Demande d'accès | Demande d'effacement |
|---|---|---|
| Les enregistrements (registrations) : inscription, cours, enregistrement | Exportés | Conservés avec l'inscription |
| Les sessions de lancement : activité, état, lancement et fin | Exportées | Supprimées |
| Les énoncés de l'entrepôt à l'intérieur du bundle | Exportés, chacun sous la forme du JSON envoyé, avec le moment où il a été conservé | Supprimés |
| Les documents d'état | Exportés, par enregistrement, identifiant d'état et SHA-256 de l'IRI de l'activité, le document sous forme de JSON | Supprimés |
| Les notes des énoncés `satisfied` émis, par bloc ou par cours | Exportées | Supprimées, sauf si les énoncés sont conservés hors du bundle |

L'effacement peut être relancé sans dommage. Son rapport nomme chaque table, et l'exportation est vide pour un apprenant
sans enregistrement cmi5.

### Ce que conserve un entrepôt extérieur au bundle

Un entrepôt extérieur au bundle conserve ce qu'on lui a envoyé, et la plateforme ne peut pas l'y effacer :

- **Un entrepôt xAPI distant**, `phil_courses.cmi5.remote_lrs`, conserve les énoncés. xAPI ne donne aucun moyen
  d'effacer un énoncé : un énoncé ne peut qu'être annulé par un autre, ce qui conserve les deux. Les documents d'état
  restent dans l'entrepôt à l'intérieur du bundle, et sont effacés.
- **L'entrepôt propre à l'application**, `phil_courses.cmi5.lrs`, conserve les énoncés et les documents d'état, que le
  bundle ne lit ni n'efface.

L'exportation le dit alors sous `kept_elsewhere` : les enregistrements que l'entrepôt conserve, l'entrepôt — le point de
terminaison de l'entrepôt distant, sans ses identifiants, ou la classe de l'adaptateur de l'application —, les enregistrements (registrations), par lesquels les énoncés s'y retrouvent, et une note. Le rapport d'effacement nomme cet entrepôt
`learning_record_store`, avec l'effet `survives`. Les notes des énoncés `satisfied` survivent aussi, puisque les énoncés
qu'elles représentent sont encore dans cet entrepôt : un énoncé `satisfied` n'est jamais envoyé deux fois. La personne,
ou le site, s'adresse à l'exploitant de cet entrepôt, avec les registrations de l'exportation.

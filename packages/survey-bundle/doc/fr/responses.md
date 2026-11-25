<!-- phil-dev source-sha256=8ca5a0875286364e29ef4d05b0dfeb66ba5524a0eb871fc89b5248515c73e84b -->
# Réponses

`Submissions::submit($questionnaire, $input, $respondent, $subject)` reçoit une réponse. Le serveur la juge quoi que le
navigateur ait montré :

1. le questionnaire est ouvert — sa fenêtre et, pour la rétroaction d'une séance, la fenêtre de la séance — et le
   répondant peut répondre : un membre quand il en faut un, une seule fois quand le répondant est connu ;
2. `phil/form-logic` juge la réponse : les questions masquées sont écartées, sans vérification, et les variables
   calculées sont calculées ;
3. chaque question affichée vérifie sa réponse ; `InvalidResponse` porte toutes les erreurs à la fois, par nom de
   question ;
4. la première fin dont la condition est vraie est choisie, et les quotas que la réponse remplit sont trouvés ;
5. la réponse, la marque du répondant et les compteurs des quotas sont écrits en une seule transaction.

Un refus lance `SurveyRefused`, dont la `reason` est une clé que les gabarits traduisent : `closed`,
`already_answered`, `member_required`, `subject_required`, `results_private`, `addenda_closed`, `unknown_receipt` et
`untrusted_request`.

Le `Receipt` contient la fin atteinte, si la réponse a été conservée, son identifiant, la redirection, les variables
calculées, le quota qui l'a écartée le cas échéant, et le jeton d'addendum quand le questionnaire accepte des addenda.

## Fins et profils

Les fins sont essayées dans l'ordre et la première dont le `when` est vrai est affichée ; la dernière n'a aucune
condition : chaque répondant en atteint donc une. Une fin peut nommer un `profile`, le résultat remis au répondant,
comme une tranche de pointage, et un `message`.

Une fin peut aussi `redirect`. L'adresse est lue par `phil/url`, et son schéma, son hôte et son port doivent être une
des `redirect_origins` ; `localhost` n'est un hôte que si `allow_localhost` est activé. Elle est vérifiée quand le
questionnaire est enregistré, puis de nouveau quand le répondant y est envoyé : un questionnaire enregistré avant le
retrait d'une origine ne redirige donc nulle part. Le bundle ne redirige jamais vers une adresse que porte la requête.

## Quotas

Un quota accepte `limit` réponses qui remplissent sa condition. Un quota croisé est une condition portant sur plusieurs
questions :

```json
{"name": "young_north", "limit": 50, "ending": "full", "when": {"and": [{"==": [{"var": "age"}, "18-24"]}, {"==": [{"var": "region"}, "north"]}]}}
```

Une réponse compte dans chaque quota qu'elle remplit. Les décomptes sont des compteurs atomiques, une ligne par
questionnaire et par quota, augmentés par une mise à jour conditionnelle — `taken < limit` — dans la transaction de la
réponse, jamais calculés en comptant les réponses. Quand l'un d'eux est plein, la transaction est annulée : la réponse
n'est pas conservée, et le répondant voit la fin de ce quota. Les compteurs sont créés hors de la transaction, et un
interblocage est retenté. Les tests d'intégration exécutent des processus simultanés contre un seul quota sur MySQL et
PostgreSQL et vérifient qu'il ne prend jamais plus que sa limite.

## Anonymat

La page dit si un questionnaire est anonyme avant sa première question. Une réponse anonyme ne conserve :

- aucun répondant et aucune adresse IP — les tables n'ont aucune colonne pour en garder une ;
- un identifiant aléatoire, un UUID v4, qui ne contient aucune heure ;
- le jour de l'envoi, à minuit UTC, et non l'heure.

Pour qu'un membre réponde une seule fois, le bundle conserve à part une marque de participation : le questionnaire, le
sujet et une empreinte SHA-256 à clé de l'identifiant du membre, sous une clé dérivée de `secret` par HKDF-SHA256 pour cet usage seul
(`Participations::USAGE`). La marque n'a aucune heure et aucune
référence à la réponse : rien ne relie donc les deux. Une réponse identifiée conserve l'identifiant du membre ; c'est
le seul genre que trouvent l'exportation et l'effacement de la vie privée.

## Rétroaction de séance

Un questionnaire `session_feedback` reçoit des réponses sur un sujet, donné comme la valeur `subject` de la requête ou
du formulaire. Le service `FeedbackSessions` de l'application donne le début et la fin de chaque séance ; la
rétroaction s'ouvre `opens_before` minutes avant le début et se ferme `closes_after` minutes après la fin. Sans service
`FeedbackSessions`, aucune rétroaction de séance n'est ouverte. Elle est anonyme par défaut, comme tout questionnaire.

## Addenda

Quand `addenda` est activé, le reçu porte un jeton de 32 octets aléatoires que seul le répondant détient ; le bundle
en conserve le SHA-256. `Addenda::add($questionnaire, $token, $text)` ajoute à la réponse un texte d'au plus 5000
caractères, jusqu'à dix par réponse, chacun avec l'instant de son ajout.

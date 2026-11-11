<!-- phil-dev source-sha256=24625813fa927e78af566c38b95ea8d2d8c8bd7be6dece30b43623199b53ae6d -->
# Analyse

`BounceParser::parse(string $raw): BounceReport` prend un message tel que reçu, champs d'en-tête et corps, et ne lève
jamais d'exception. Il lit le message avec `Mime\MimeParser`, puis essaie chaque lecture dans l'ordre ; la première qui
reconnaît le message décide de son `ReportFormat`.

| Ordre | Format | Reconnu par |
|---|---|---|
| 1 | `DeliveryStatus` | Une partie `message/delivery-status` ou `message/global-delivery-status` |
| 2 | `FeedbackReport` | Une partie `message/feedback-report` |
| 3 | `PlainText` | Un expéditeur `MAILER-DAEMON`, `mail-daemon` ou `postmaster`, le chemin de retour nul `<>`, ou un objet comme en écrivent ces serveurs |
| 4 | `AutoReply` | `Auto-Submitted` autre que `no`, `X-Autoreply`, `X-Autorespond`, `Precedence: auto_reply` |
| 5 | `Unrecognised` | Rien de tout cela |

Les parties d'un message que contient le rapport ne sont pas fouillées à la recherche d'un rapport : une notification
transférée dans un message ne compte pas comme telle. Les rebonds écrits en texte passent avant les réponses
automatiques parce que plusieurs serveurs marquent leurs rebonds `Auto-Submitted: auto-replied`.

Ensuite, les règles sont essayées — voir [Règles](rules.md).

## Notifications d'état de remise

La partie d'état est lue comme des groupes de champs séparés par des lignes vides. Chaque groupe qui a un
`Final-Recipient`, ou à défaut un `Original-Recipient`, est un destinataire : son adresse — le domaine en minuscules —,
`Action`, `Status` (le code, sans son commentaire), `Diagnostic-Code`, le code SMTP de base au début du diagnostic,
`Remote-MTA` et `Last-Attempt-Date`. Un état manquant est cherché dans le diagnostic. Le genre du rapport est le pire de
ses destinataires : dur, puis temporaire, puis remis ; une notification sans destinataire est de genre inconnu.

## Rebonds écrits en texte

Les parties texte du message, et non celles du message qu'il retourne, jusqu'à la limite de texte, sont fouillées à la
recherche de la première adresse qui n'appartient pas à un serveur de courriel, du premier code d'état étendu, et du
premier code SMTP en début de ligne ou après un deux-points. Sans code, le genre est inconnu et seule une règle peut
trancher ; sans adresse, le rapport ne nomme aucun destinataire.

## Réponses automatiques

Le destinataire est le `From` de la réponse ; le message auquel elle répond est son `In-Reply-To`, sauf si elle retourne
le message.

## Le rapport

| Membre | Contient |
|---|---|
| `format`, `kind`, `action` | Comment il a été reconnu, ce qu'il dit, quoi faire — voir [Genres et actions](kinds-and-actions.md) |
| `message` | La `Mime\Part` du message entier |
| `recipients` | Chaque `Recipient` que nomme le rapport |
| `feedback` | Le `FeedbackReport` d'un rapport de plainte |
| `original` | L'`OriginalMessage` qu'il concerne — voir [Retrouver le message](finding-the-message.md) |
| `verpTag` | L'étiquette du chemin de retour qui l'a reçu, quand un `Verp` est donné |
| `rule` | Le nom de la règle qui a décidé, ou `null` |
| `authentic` | Si l'authentificateur s'est porté garant du rapport de plainte |
| `isRecognised()` | Si son genre est connu |
| `addresses()` | Les adresses des destinataires pour lesquels le message a échoué, ou de tous quand aucun n'a échoué, chacune une fois |

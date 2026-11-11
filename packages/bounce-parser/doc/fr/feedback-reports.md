<!-- phil-dev source-sha256=5871058be50688f300b577645054ff3eebc40e7e0ed3cad77afe0a0a27186c14 -->
# Rapports de plainte

Un rapport de plainte pour abus (RFC 5965) est ce qu'un fournisseur de boîtes de courriel envoie par une boucle de
rétroaction quand un destinataire marque un message comme pourriel. L'analyseur lit sa partie `message/feedback-report`
dans un `FeedbackReport`.

| Membre | Champ |
|---|---|
| `type` | `Feedback-Type`, un `FeedbackType` : `Abuse`, `Fraud`, `Virus`, `Other` (et tout type que la liste ne connaît pas), `NotSpam`, `AuthFailure` |
| `userAgent`, `version` | `User-Agent`, `Version` |
| `originalMailFrom` | `Original-Mail-From`, l'adresse seule |
| `originalRcptTo` | Chaque `Original-Rcpt-To`, l'adresse seule ; les fournisseurs les omettent souvent |
| `arrivalDate` | `Arrival-Date`, sinon `Received-Date`, tel qu'écrit |
| `reportingMta` | `Reporting-MTA`, sans son type |
| `sourceIp` | `Source-IP` |
| `incidents` | `Incidents`, 1 s'il manque ou n'est pas un nombre |
| `reportedDomain`, `reportedUri` | Chaque `Reported-Domain`, `Reported-URI` |
| `authenticationResults` | Les `Authentication-Results` que le fournisseur a écrits dans le rapport |

Les destinataires du rapport sont ses `Original-Rcpt-To`, ou le `To` du message qu'il retourne quand il n'en a aucun.
Bien des fournisseurs cachent les deux : l'application retrouve alors le destinataire à partir du message, par son
`Message-ID`, son `Feedback-ID`, un champ qui lui est propre, ou son étiquette VERP — voir [Retrouver le
message](finding-the-message.md).

## Authentification

N'importe qui peut écrire un rapport de plainte à la boîte de rebonds, et une plainte désabonne. L'analyseur demande
donc à un `Contract\ReportAuthenticator` si le rapport vient d'une boucle de rétroaction à laquelle l'application est
abonnée ; `BounceReport::$authentic` contient la réponse. Sans authentificateur, ou quand il ne se porte pas garant, une
plainte va à `BounceAction::Review`.

`Auth\AuthenticationResults` se porte garant d'un rapport dont le message porte un champ `Authentication-Results`
(RFC 8601) écrit sous l'un des authserv-id de l'application — le nom sous lequel son propre serveur de courriel écrit
ses résultats — qui consigne `dkim=pass` avec un `header.d` égal à l'un des domaines de confiance, ou à l'un de leurs
sous-domaines. Les champs des autres authserv-id sont ignorés : n'importe qui peut en écrire un. Le serveur de courriel
doit retirer des messages qu'il reçoit tout champ qui revendique son propre authserv-id, comme le demande la RFC 8601 ;
la plupart des serveurs le font, vérifiez le vôtre.

`Auth\TrustEveryReport` se porte garant de tout rapport : pour une boîte où seules les boucles de rétroaction peuvent
écrire, son adresse n'étant connue de personne d'autre.

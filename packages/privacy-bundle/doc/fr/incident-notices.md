<!-- phil-dev source-sha256=5e784f9cc8b2b10f8ce80870c615b958593b8d5ee369733af62cfe71aeb28787 -->
# Les brouillons des avis d'un incident

Quand un incident de confidentialité présente un risque de préjudice sérieux, l'entreprise avise la Commission d'accès à
l'information et les personnes concernées. `phil:privacy:incident-notice` écrit en Markdown un **brouillon** de chaque
avis, rempli à partir du registre des incidents et de l'identité de l'organisation. Le bundle n'envoie jamais rien : la
personne responsable de la protection des renseignements personnels juge si un avis est dû, révise le brouillon, le
complète et l'envoie.

Cette page explique ce que le bundle fait du règlement ; ce n'est pas un avis juridique.

## Ce que le règlement demande des avis

Le *Règlement sur les incidents de confidentialité* (chapitre A-2.1, r. 3.1) fixe le contenu de chaque avis :

| Article | Ce qu'il demande | Brouillon |
|---|---|---|
| art. 3 | L'avis écrit à la Commission, en onze paragraphes, énumérés ci-dessous. | `--to=commission` |
| art. 4 | Ce que l'entreprise apprend après son avis, parmi ces paragraphes, est transmis sans délai à la Commission. | `--to=commission --follow-up` |
| art. 5 | L'avis à chaque personne concernée, en six paragraphes, énumérés ci-dessous. | `--to=subject` |
| art. 6 | L'avis est donné à chaque personne. Il devient un avis public quand l'envoyer causerait un préjudice accru à la personne, serait excessivement difficile, ou quand l'entreprise n'a pas les coordonnées des personnes. Un avis public peut aussi être donné pour agir rapidement ; l'avis à chaque personne reste alors dû, sauf dans ces trois cas. | `--to=public` |

Le devoir d'aviser vient de l'article 3.5 de la *Loi sur la protection des renseignements personnels dans le secteur
privé* (chapitre P-39.1) ; le règlement s'applique aux organismes publics, aux entreprises, aux ordres professionnels et
aux partis politiques. Voir [Le registre des incidents](incidents.md) pour le registre lui-même.

## Qui décide, qui envoie

| Étape | Qui |
|---|---|
| Si le risque de préjudice est sérieux (P-39.1, art. 3.7), après consultation de la personne responsable de la protection des renseignements personnels | L'entreprise |
| Ce que dit l'avis, et les rubriques que le registre ne peut pas remplir | La personne responsable |
| L'envoi de l'avis, par écrit à la Commission, à chaque personne, ou publiquement | La personne responsable |
| L'inscription de la date de l'avis dans le registre | La personne responsable, avec `phil:privacy:incident` |
| L'écriture d'un brouillon à partir de ce que contient le registre | `phil:privacy:incident-notice` |

La commande n'appelle ni expéditeur de courriels, ni notificateur, ni bus de messages, ni client HTTP, n'écrit rien dans
le registre et ne journalise jamais le contenu d'un brouillon.

## Écrire un brouillon

```bash
bin/console phil:privacy:incident-notice 4f3c… --to=commission --locale=fr --output=avis-commission.md
bin/console phil:privacy:incident-notice 4f3c… --to=subject --locale=en
bin/console phil:privacy:incident-notice 4f3c… --to=public
bin/console phil:privacy:incident-notice 4f3c… --to=commission --follow-up
bin/console phil:privacy:incident-notice 4f3c… --to=commission --follow-up --since="2026-11-14 10:00"
```

| Option | Règle |
|---|---|
| l'argument | L'identifiant de l'incident dans le registre. |
| `--to` | `commission`, `subject` ou `public` ; obligatoire. |
| `--locale` | `fr` (par défaut) ou `en`, la langue du brouillon. |
| `--output` | Un nouveau fichier où écrire le brouillon, lisible par son propriétaire seulement ; un fichier qui existe est refusé. Sans cette option, le brouillon va à la sortie standard. |
| `--follow-up` | Avec `--to=commission`, le suivi de l'article 4. |
| `--since` | Avec `--follow-up`, la date du dernier avis ou suivi envoyé ; par défaut, la date de l'avis à la Commission inscrite dans le registre. |

Le brouillon va seul à la sortie standard, de sorte que `> notice.md` ne garde que lui. Les rubriques à compléter, les
rappels et la prochaine étape vont à la sortie d'erreur, ou à la sortie standard après un brouillon écrit avec
`--output` :

```text
To complete in the draft: s. 3, para. 4 (cause); s. 3, para. 7 (affected in quebec); s. 3, para. 11 (other authority).
Nothing was sent. Review the draft, send it to the Commission in writing, then record the date: phil:privacy:incident --id=4f3c… --authority-notified=<date>.
```

Les dates sont écrites dans le fuseau horaire de l'horloge de l'application, sans l'heure quand il est minuit.

## Les rubriques et leur origine

Chaque rubrique que le registre ou l'organisation connaît est remplie. Une rubrique qu'ils ne connaissent pas reste à sa
place dans le brouillon, marquée en gras, `**To complete: the cause of the incident, if known (s. 3, para. 4)**`, et la
commande la liste après le brouillon, comme `phil:privacy:incident` liste les paragraphes du registre qui manquent
encore.

### L'avis à la Commission (article 3)

| Par. | Rubrique | Origine |
|---|---|---|
| 1 | Le nom de l'organisation et, s'il y en a un, son numéro d'entreprise du Québec (NEQ) | L'organisation, ci-dessous |
| 2 | Le nom et les coordonnées de la personne à joindre au sujet de l'incident | L'organisation, ci-dessous |
| 3 | Les renseignements personnels visés, ou la raison pour laquelle on ne peut les décrire | `categories`, `information`, `undescribedReason` |
| 4 | Une brève description des circonstances | `summary` |
| 4 | La cause, si elle est connue | Toujours à compléter |
| 5 | La date ou la période de l'incident, ou une approximation | `occurredAt`, `occurredUntil`, `occurrenceApproximate` |
| 6 | La date ou la période où l'organisation en a pris connaissance | `discoveredAt`, `discoveredUntil` |
| 7 | Le nombre de personnes concernées, ou une approximation | `affected`, `affectedApproximate` |
| 7 | Combien d'entre elles résident au Québec, ou une approximation | Toujours à compléter |
| 8 | Ce qui mène à conclure qu'il existe un risque de préjudice sérieux | `riskAssessment` |
| 9 | Les mesures prises ou prévues pour aviser les personnes, avec la date ou le délai prévu | `subjectsNotifiedAt`, `publicNoticeAt` et sa raison ; à compléter tant qu'aucun des deux n'est inscrit |
| 10 | Les mesures prises ou prévues pour réduire le risque et éviter un nouvel incident, avec leurs dates | `measures` ; écrivez-y leurs dates |
| 11 | Qu'une autorité hors du Québec ayant des fonctions semblables a été avisée, s'il y en a une | Toujours à compléter |

### L'avis à chaque personne, ou au public (articles 5 et 6)

| Par. | Rubrique | Origine |
|---|---|---|
| 1 | Les renseignements personnels visés, ou la raison pour laquelle on ne peut les décrire | `categories`, `information`, `undescribedReason` |
| 2 | Une brève description des circonstances | `summary` |
| 3 | La date ou la période de l'incident, ou une approximation | `occurredAt`, `occurredUntil`, `occurrenceApproximate` |
| 4 | Les mesures prises ou prévues pour réduire le risque de préjudice | `measures` |
| 5 | Les mesures que l'organisation suggère à la personne pour réduire le risque ou atténuer le préjudice | Toujours à compléter |
| 6 | La personne à joindre pour en savoir plus | L'organisation, ci-dessous |

Les brouillons destinés aux personnes leur parlent sous des titres simples (*What happened*, *When*, *What we suggest you
do*…) ; un commentaire HTML sous chaque titre nomme son paragraphe, et l'identifiant de l'incident, de sorte que le
brouillon reste traçable sans le montrer au lecteur.

### Ce que le registre ne garde pas

Quatre rubriques de l'article 3 et une de l'article 5 n'ont aucun champ dans le registre : la cause, le nombre de
personnes résidant au Québec, une autorité hors du Québec, et les mesures suggérées aux personnes, avec les plans et les
dates des paragraphes 9 et 10 au-delà de ce que disent `measures` et les dates des avis. L'article 7 ne les demande pas
au registre ; ce sont des faits que la personne responsable énonce dans l'avis lui-même. Le brouillon les marque
toujours à compléter, plutôt que d'ajouter des colonnes à un registre qui n'en a pas besoin.

### Qui donne l'avis

L'organisation vient d'une `NoticeOrganisationSource`. Avec phil/legal-notices-bundle installé, le bundle lit
l'identité du site :

| Rubrique | Champ de l'identité |
|---|---|
| Le nom de l'organisation | `publisher_name` |
| Le numéro d'entreprise du Québec | `publisher_registration` ; une entreprise du Québec y met son NEQ |
| La personne à joindre | `dpo_name` |
| La façon de la joindre | `dpo_contact` |

Une application nomme sa propre source en faisant de `Phil\PrivacyBundle\Application\Contract\NoticeOrganisationSource`
un alias d'un service à elle ; sans l'un ni l'autre, ces rubriques sont laissées à compléter. Voir
[extension-points.md](extension-points.md#noticeorganisationsource).

## Le suivi à la Commission (article 4)

`--follow-up` liste les rubriques de l'article 3 qu'une correction a changées après le dernier avis : il lit les
corrections que garde le registre (`IncidentHistory`, la table `phil_privacy_incident_revision`) et retient celles
inscrites après la date de l'avis à la Commission, `authorityNotifiedAt`, ou après `--since`. Un paragraphe changé est
répété en entier, et l'organisation et la personne à joindre ouvrent le brouillon. Quand rien n'a changé, la commande le
dit et n'écrit aucun brouillon ; sans avis inscrit et sans `--since`, ou avec un registre qui ne garde aucune
correction, elle refuse.

Le registre garde une seule date d'avis à la Commission. Gardez la date de chaque suivi envoyé et donnez-la à `--since`
pour le suivant.

## L'avis public (article 6)

`--to=public` est refusé pour un incident sans raison d'avis public, avec un message qui nomme les trois cas de
l'article 6. Inscrivez d'abord la raison :

```bash
bin/console phil:privacy:incident --id=4f3c… --public-notice=2026-11-12 --public-notice-reason=no_contact_details
```

La raison est écrite en commentaire HTML en tête du brouillon. Avec `increased_injury`, `excessive_hardship` ou
`no_contact_details`, l'avis public tient lieu de l'avis à chaque personne ; avec `prompt_action`, la commande rappelle
que l'avis à chaque personne reste dû avec diligence. Un brouillon destiné à chaque personne rappelle les trois cas où
un avis public le remplace.

## Après l'envoi

La commande n'inscrit rien. Une fois l'avis envoyé, inscrivez sa date :

```bash
bin/console phil:privacy:incident --id=4f3c… --authority-notified="2026-11-11 10:30"
bin/console phil:privacy:incident --id=4f3c… --subjects-notified=2026-11-12
```

## Modifier les brouillons

Les brouillons sont des gabarits Twig dont le rendu est du Markdown, dans `@PhilPrivacy/incident_notice/` :
`commission.md.twig`, `follow_up.md.twig`, `subject.md.twig`, `public.md.twig`, les rubriques partagées dans
`_commission_rubrics.md.twig` et `_people_rubrics.md.twig`, et les morceaux dans `_macros.md.twig`. Une application en
remplace un dans `templates/bundles/PhilPrivacy/incident_notice/`. Leurs textes sont dans le domaine de traduction
`phil_privacy`, sous `incident_notice.`, en français et en anglais ; remplacez un texte dans le répertoire
`translations/` de l'application.

Un gabarit reçoit :

| Variable | Ce qu'elle contient |
|---|---|
| `incident` | L'`Incident`. |
| `organisation` | La `NoticeOrganisation` : `name`, `enterpriseNumber`, `contactName`, `contactDetails`, nuls quand ils ne sont pas connus. |
| `locale` | `fr` ou `en`. |
| `rubric` | Pour chaque rubrique par son nom, sa `section`, son `paragraph`, si elle est `shown` et si elle est `missing`. |
| `dates` | `discovered`, `discovered_until`, `occurred`, `occurred_until`, `subjects_notified`, `public_notice` et `since`, écrites dans le fuseau horaire de l'horloge, ou nulles. |

La commande a besoin de Twig et du traducteur, en plus du registre et de l'horloge ; sans eux, elle n'est pas
enregistrée.

## Ce qui n'est pas couvert

- L'avis au titulaire de l'autorité parentale pour une personne de moins de quatorze ans, que le règlement ne traite
  pas.
- La communication à une personne ou à un organisme susceptible de réduire le risque (P-39.1, art. 3.5, al. 2), l'avis
  différé pour une enquête (al. 3), et l'inscription de la consultation de la personne responsable (art. 3.7).

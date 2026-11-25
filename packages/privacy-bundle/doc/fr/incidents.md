<!-- phil-dev source-sha256=17e82b77e945d32168a481ff8fded3b2d199956856d5823b300577c4df2c46d9 -->
# Le registre des incidents de confidentialité

La loi demande à une entreprise de tenir un registre de **chaque** incident de confidentialité — un renseignement
personnel consulté, utilisé, communiqué ou perdu sans autorisation —, grave ou non, et d'aviser l'autorité et les
personnes concernées de celui qui présente un risque de préjudice sérieux. Le bundle tient ce registre, avec les huit
paragraphes que le Québec demande pour chaque ligne, et le garde au moins cinq ans.

## Ce que demande le Québec

La *Loi sur la protection des renseignements personnels dans le secteur privé* (chapitre P-39.1) fixe les devoirs, et le
*Règlement sur les incidents de confidentialité* (chapitre A-2.1, r. 3.1) le contenu des avis et du registre :

| Texte | Ce qu'il demande | Ce que fait le bundle |
|---|---|---|
| P-39.1, art. 3.6 | Un incident est un accès, une utilisation ou une communication que la loi n'autorise pas, une perte, ou toute autre atteinte à la protection d'un renseignement personnel. | Chaque incident de ce genre est une ligne, grave ou non. |
| P-39.1, art. 3.5, al. 1 | Dès qu'elle a des raisons de croire qu'un incident s'est produit, l'entreprise prend les mesures raisonnables pour réduire le risque de préjudice et pour éviter qu'un incident de même nature se produise. | `--measures`, paragraphe 8 du registre. |
| P-39.1, art. 3.5, al. 2 | Si le risque de préjudice est sérieux, l'entreprise avise avec diligence la Commission d'accès à l'information et chaque personne concernée. | `pendingNotifications()` nomme les avis encore dus après chaque inscription ; `--authority-notified` et `--subjects-notified` les datent. |
| P-39.1, art. 3.5 | L'avis à une personne concernée n'est pas donné tant qu'il pourrait entraver une enquête menée par une personne ou un organisme chargé par la loi de prévenir, détecter ou réprimer le crime. | Un `notice_deferral` dans le journal de l'incident, avec l'organisme et la raison, et un `deferral_lifted` dès que l'enquête n'est plus gênée ([plus bas](#le-journal-dun-incident)). |
| P-39.1, art. 3.5 | L'entreprise peut communiquer, sans consentement, des renseignements personnels visés par l'incident à une personne ou un organisme susceptible de réduire le risque, en ne donnant que ce que ce but exige ; la personne responsable de la protection des renseignements personnels inscrit la communication. | Une `third_party_communication` dans le journal de l'incident : quand, à qui, quels renseignements et pourquoi. |
| P-39.1, art. 3.7 | Le risque se juge selon la sensibilité du renseignement, les conséquences appréhendées de son utilisation et la probabilité qu'il soit utilisé à des fins préjudiciables, après consultation de la personne responsable de la protection des renseignements personnels. | `--risk-assessment`, paragraphe 6 du registre, et une `officer_consultation` dans le journal de l'incident : quand, qui a été consulté, et ce qu'il a répondu. Le jugement et la consultation reviennent à cette personne. |
| P-39.1, art. 3.8 | L'entreprise tient un registre des incidents, et en transmet copie à la Commission à sa demande. | La table `phil_privacy_incident` ; `phil:privacy:incidents` imprime la copie. |
| r. 3.1, art. 3 à 5 | Le contenu de l'avis à la Commission, de ses suivis, et de l'avis à chaque personne. | `phil:privacy:incident-notice` en écrit un brouillon de chacun à partir du registre ([incident-notices.md](incident-notices.md)). |
| r. 3.1, art. 6 | L'avis à la personne devient un avis public quand l'envoyer causerait un préjudice accru, serait excessivement difficile, ou quand l'entreprise n'a pas les coordonnées de la personne ; un avis public peut aussi être donné pour agir rapidement, l'avis à chaque personne restant dû. | `--public-notice` et `--public-notice-reason` ; les trois premières raisons tiennent lieu de l'avis à chaque personne ; `phil:privacy:incident-notice --to=public` ne rédige l'avis public que pour un incident qui a une raison. |
| r. 3.1, art. 7 | Les huit paragraphes de chaque ligne du registre, ci-dessous. | Chacun a ses champs ; `missingParagraphs()` nomme ceux auxquels une ligne ne répond pas encore. |
| r. 3.1, art. 8 | Le registre est tenu à jour et gardé au moins cinq ans après la date ou la période où l'entreprise a pris connaissance de l'incident. | Les lignes sont complétées et corrigées, leurs corrections étant gardées ; une règle de conservation plus courte fait échouer la construction, et la purge ne descend jamais sous cinq ans. |

Les avis à la Commission et aux personnes (articles 3 à 6 du règlement) sont jugés, révisés et envoyés par la personne
responsable de la vie privée ; `phil:privacy:incident-notice` en écrit les brouillons à partir du registre, et le bundle
en inscrit les dates. Voir [Les brouillons des avis](incident-notices.md). Les lignes directrices de la Commission du
30 janvier 2026 sur les incidents de confidentialité expliquent comment juger le risque et ce que disent les avis. Le
RGPD demande le même registre des violations (article 33(5)) et un avis à l'autorité dans les 72 heures. Voir
[legal-framework.md](legal-framework.md).

## Inscrire et compléter une ligne

```bash
bin/console phil:privacy:incident "Mailing list exported to a public folder" \
    --occurred="2026-11-09 08:00" --category=email --category=person_name --affected=120 --serious-risk
# Incident 4f3c… recorded.
# It presents a risk of serious injury: notify the authority promptly, then record it with --authority-notified.
# It presents a risk of serious injury: notify the people concerned promptly, then record it with --subjects-notified.
# Still to record for section 7 of the regulation: paragraph 6 (the assessment of the risk: --risk-assessment), 7 (the notifications still owed), 8 (the measures: --measures).

bin/console phil:privacy:incident --id=4f3c… --measures="Folder removed, link revoked" --authority-notified="2026-11-10 15:00" \
    --risk-assessment="Names and email addresses, folder indexed by search engines: misuse is likely."
bin/console phil:privacy:incident --id=4f3c… --subjects-notified=2026-11-11 --closed=2026-11-20

bin/console phil:privacy:incidents --format=markdown > incidents.md
```

Une ligne contient, paragraphe par paragraphe de l'article 7 du règlement :

| Paragraphe | Champ | Option | Règle |
|---|---|---|---|
| 1. Les renseignements personnels concernés | catégories | `--category`, répétée | Les sortes de renseignements personnels, comme jetons `DataCategory`. |
| | renseignements | `--information` | Une description des renseignements, au plus 4 000 caractères. |
| | raison de l'absence de description | `--undescribed-reason` | Pourquoi les renseignements ne peuvent pas être décrits, au plus 4 000 caractères ; jamais avec une description. |
| 2. Les circonstances | résumé | l'argument | Ce qui s'est passé ; obligatoire, au plus 4 000 caractères. |
| 3. La date ou la période de l'incident | survenue | `--occurred` | Quand il s'est produit, ou le début de la période, si on le sait ; jamais après la fin de la découverte. |
| | survenue jusqu'à | `--occurred-until` | La fin de la période ; jamais avant son début, et seulement avec `--occurred`. |
| | survenue approximative | `--approximate-occurrence`, `--no-approximate-occurrence` | Si la date ou la période est une approximation ; seulement avec `--occurred`. |
| 4. La date ou la période de la découverte | découverte | `--discovered` | Quand l'entreprise en a pris connaissance, ou le début de la période ; maintenant par défaut. |
| | découverte jusqu'à | `--discovered-until` | La fin de la période ; jamais avant son début. |
| 5. Le nombre de personnes | personnes touchées | `--affected` | Combien de personnes, si on le sait. |
| | nombre approximatif | `--approximate-affected`, `--no-approximate-affected` | Si le nombre est une approximation ; seulement avec `--affected`. |
| 6. Le risque de préjudice sérieux | risque sérieux | `--serious-risk`, `--no-serious-risk` | S'il présente un risque de préjudice sérieux. |
| | évaluation du risque | `--risk-assessment` | Ce qui mène à cette conclusion, sérieux ou non, au plus 4 000 caractères. |
| 7. Les avis | autorité avisée | `--authority-notified` | Quand la Commission a été avisée. |
| | personnes avisées | `--subjects-notified` | Quand les personnes concernées ont été avisées. |
| | avis public | `--public-notice`, `--public-notice-reason` | Quand un avis public a été donné et pourquoi : `increased_injury`, `excessive_hardship`, `no_contact_details` ou `prompt_action` ; l'un jamais sans l'autre. |
| 8. Les mesures | mesures | `--measures` | Ce qui a été fait pour réduire le risque, au plus 4 000 caractères. |
| — | fermeture | `--closed` | Quand il a été fermé. |

Aucun avis et aucune fermeture ne peuvent précéder le début de la découverte. Les paragraphes 2 et 4 sont requis pour
inscrire une ligne ; les autres s'inscrivent quand on les connaît, et après chaque inscription la commande nomme ceux
qui manquent encore (`Incident::missingParagraphs()`) : le 1 sans description ni raison de son absence, le 3 sans date
de l'incident, le 5 sans nombre, le 6 sans évaluation, le 7 tant qu'un avis est encore dû, le 8 sans mesures. Avec un
risque de préjudice sérieux, elle nomme aussi les avis encore dus (`Incident::pendingNotifications()`) ; un avis public
donné pour l'une des trois premières raisons tient lieu de l'avis à chaque personne. Juger si un risque est sérieux, et
les avis eux-mêmes, reviennent à la personne responsable de la vie privée.

### Corriger une ligne

Avec `--id`, la commande complète ou corrige une ligne : une valeur omise garde celle qu'elle avait, le résumé donné en
argument remplace le précédent, et `--clear` vide une date ou un nombre inscrit par erreur (`occurred`,
`occurred-until`, `discovered-until`, `affected`, `authority-notified`, `subjects-notified`, `public-notice`, `closed`).
Un texte se vide par une valeur vide de son option. Vider `occurred` vide aussi la fin de l'incident et son
approximation ; vider `affected`, son approximation.

Une correction ne perd jamais ce que disait le registre : la table `phil_privacy_incident_revision` garde, pour chaque
champ qu'une sauvegarde change, sa valeur précédente et l'instant de la correction, numérotés par incident, dans la même
transaction (`IncidentHistory::revisions()`). Une ligne n'est jamais retirée à la main.

## Le journal d'un incident

À côté des huit paragraphes, les articles 3.5 et 3.7 de la loi P-39.1 laissent des gestes dont il faut garder la trace :
une communication à un tiers qui pourrait réduire le risque, un avis reporté pour une enquête, et la consultation de la
personne responsable de la protection des renseignements personnels. Chacun est une entrée du journal de l'incident,
numérotée à partir de 1 et à laquelle on ne fait jamais qu'ajouter :

```bash
bin/console phil:privacy:incident-journal 4f3c… --kind=third_party_communication --at="2026-11-10 10:00" \
    --party="Card issuer" --details="Card numbers of the 120 clients, to block the cards"
bin/console phil:privacy:incident-journal 4f3c… --kind=officer_consultation --party="Privacy officer" \
    --details="Asked whether the risk is serious: yes, card numbers with names"
bin/console phil:privacy:incident-journal 4f3c… --kind=notice_deferral --party="Municipal police" \
    --details="Notifying the clients would warn the suspect" --until=2026-12-10
bin/console phil:privacy:incident-journal 4f3c… --kind=deferral_lifted --party="Municipal police"
# Entry 4 of the journal of incident 4f3c… recorded (section 3.5 of chapter P-39.1).
# The notice put off is now owed promptly: record it with phil:privacy:incident --subjects-notified.

bin/console phil:privacy:incident-journal 4f3c…
# 1. 2026-11-10T15:00:00+00:00, third_party_communication — Card issuer: Card numbers of the 120 clients, to block the cards
# …
```

| Genre (`--kind`) | Article | `--party` | `--details` |
|---|---|---|---|
| `third_party_communication` | 3.5 | Qui a reçu le renseignement | Obligatoire : quel renseignement, et comment il pourrait réduire le risque |
| `notice_deferral` | 3.5 | L'organisme qui enquête | Obligatoire : quelle enquête l'avis pourrait gêner ; `--until`, sa fin prévue, quand elle est connue |
| `deferral_lifted` | 3.5 | L'organisme qui enquête | Facultatif |
| `officer_consultation` | 3.7 | La personne consultée | Obligatoire : ce qu'on lui a demandé, et ce qu'elle a répondu |

`--at`, le moment où le geste a été fait, vaut maintenant par défaut, jamais avant le début de la découverte et jamais
dans le futur. `--party` compte 255 caractères au plus et `--details` 4 000. Un `deferral_lifted` est refusé tant
qu'aucun report n'est en vigueur. Les avis encore dus (`pendingNotifications()`) le restent pendant un report : le
journal dit pourquoi l'un d'eux n'est pas encore donné.

En code, `Application\Incident\IncidentJournalRecorder::record()` écrit une entrée (`Domain\Incident\JournalEntry`, d'un
`JournalKind`), `entries()` les lit, et `isDeferred()` dit si un report est en vigueur ; un refus est un
`JournalRefused`. Le registre garde le journal quand il implémente `Application\Contract\IncidentJournal`, comme le fait
la table du bundle ; avec un autre stockage qui ne le fait pas, l'enregistreur refuse chaque entrée.

### Imprimer le registre

`phil:privacy:incidents` imprime le registre, l'incident découvert le plus récemment en premier, à remettre à la
Commission qui en demande copie : un tableau Markdown avec une colonne par champ et les paragraphes encore manquants,
puis un tableau des corrections et un tableau du journal ; ou du JSON avec chaque champ, `pending_notifications`,
`missing_paragraphs`, `revisions` et `journal` pour chaque ligne.

## Le garder

Le registre est la table `phil_privacy_incident`, ajoutée au schéma que génère l'ORM ; un autre stockage est un
`IncidentRegister` nommé sous `phil_privacy.incidents.store`, et les tables du registre ne sont alors pas ajoutées. Le
registre est actif par défaut ; `phil_privacy.incidents.enabled: false` le retire, avec ses commandes, quand
l'application tient son registre ailleurs. Gardez-le avec une règle de conservation sur son stockage, qui retire les
incidents fermés connus depuis avant la date limite, jamais un incident ouvert, avec leurs corrections et leur journal :

```yaml
phil_privacy:
    retention:
        rules:
            R-INC: { store: phil_privacy_incident, period: P5Y, purpose: The register of confidentiality incidents, location: Canada }
```

L'article 8 du règlement garde chaque ligne au moins cinq ans après la date ou la période de la découverte
(`Incident::MINIMUM_RETENTION`). Deux gardes tiennent ce plancher :

- **à la construction**, une période fixe sur `phil_privacy_incident` de moins de cinq ans fait échouer la construction
  du conteneur, en citant l'article : `P1Y`, `P59M` et `P1826D` sont refusées, `P5Y`, `P60M` et `P7Y` gardées
  (`RetentionPeriod::lastsAtLeast()`) ;
- **à la purge**, `DbalIncidentRegister` ne retire jamais une ligne connue depuis moins de cinq ans, quelle que soit la
  date limite qu'on lui donne, comme celle d'un `retention_schedule_source` lu à l'exécution. Une ligne découverte sur
  une période compte à partir de la fin de la période.

## Les tables

`PrivacySchemaListener` ajoute les colonnes, la table des corrections et la table du journal au schéma que génère
l'ORM ; la commande `doctrine:migrations:diff` écrit donc la migration. Sur un registre déjà en service, les nouvelles
colonnes acceptent `NULL` ou valent faux par défaut, et les lignes déjà là sont gardées. Sans l'ORM, la table du journal
est :

MySQL 8.4 et MariaDB :

```sql
CREATE TABLE phil_privacy_incident_journal (incident_id VARCHAR(32) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_bin`, sequence INT NOT NULL, kind VARCHAR(32) NOT NULL, done_at DATETIME NOT NULL, party VARCHAR(255) NOT NULL, details LONGTEXT NOT NULL, until_at DATETIME DEFAULT NULL, recorded_at DATETIME NOT NULL, PRIMARY KEY (incident_id, sequence));
```

PostgreSQL 17 :

```sql
CREATE TABLE phil_privacy_incident_journal (incident_id VARCHAR(32) NOT NULL, sequence INT NOT NULL, kind VARCHAR(32) NOT NULL, done_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, party VARCHAR(255) NOT NULL, details TEXT NOT NULL, until_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, recorded_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (incident_id, sequence));
```

SQLite :

```sql
CREATE TABLE phil_privacy_incident_journal (incident_id VARCHAR(32) NOT NULL, sequence INTEGER NOT NULL, kind VARCHAR(32) NOT NULL, done_at DATETIME NOT NULL, party VARCHAR(255) NOT NULL, details CLOB NOT NULL, until_at DATETIME DEFAULT NULL, recorded_at DATETIME NOT NULL, PRIMARY KEY (incident_id, sequence));
```

La migration d'un registre en service, écrite avant les paragraphes de l'article 7, est :

MySQL 8.4 et MariaDB :

```sql
CREATE TABLE phil_privacy_incident_revision (incident_id VARCHAR(32) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_bin`, sequence INT NOT NULL, field VARCHAR(32) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_bin`, previous LONGTEXT DEFAULT NULL, revised_at DATETIME NOT NULL, PRIMARY KEY (incident_id, sequence, field));
ALTER TABLE phil_privacy_incident ADD information LONGTEXT DEFAULT NULL, ADD undescribed_reason LONGTEXT DEFAULT NULL, ADD occurred_until DATETIME DEFAULT NULL, ADD occurrence_approximate TINYINT DEFAULT 0 NOT NULL, ADD discovered_until DATETIME DEFAULT NULL, ADD affected_approximate TINYINT DEFAULT 0 NOT NULL, ADD risk_assessment LONGTEXT DEFAULT NULL, ADD public_notice_at DATETIME DEFAULT NULL, ADD public_notice_reason VARCHAR(32) DEFAULT NULL;
```

PostgreSQL 17 :

```sql
CREATE TABLE phil_privacy_incident_revision (incident_id VARCHAR(32) NOT NULL, sequence INT NOT NULL, field VARCHAR(32) NOT NULL, previous TEXT DEFAULT NULL, revised_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (incident_id, sequence, field));
ALTER TABLE phil_privacy_incident ADD information TEXT DEFAULT NULL;
ALTER TABLE phil_privacy_incident ADD undescribed_reason TEXT DEFAULT NULL;
ALTER TABLE phil_privacy_incident ADD occurred_until TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL;
ALTER TABLE phil_privacy_incident ADD occurrence_approximate BOOLEAN DEFAULT false NOT NULL;
ALTER TABLE phil_privacy_incident ADD discovered_until TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL;
ALTER TABLE phil_privacy_incident ADD affected_approximate BOOLEAN DEFAULT false NOT NULL;
ALTER TABLE phil_privacy_incident ADD risk_assessment TEXT DEFAULT NULL;
ALTER TABLE phil_privacy_incident ADD public_notice_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL;
ALTER TABLE phil_privacy_incident ADD public_notice_reason VARCHAR(32) DEFAULT NULL;
```

SQLite :

```sql
CREATE TABLE phil_privacy_incident_revision (incident_id VARCHAR(32) NOT NULL, sequence INTEGER NOT NULL, field VARCHAR(32) NOT NULL, previous CLOB DEFAULT NULL, revised_at DATETIME NOT NULL, PRIMARY KEY (incident_id, sequence, field));
ALTER TABLE phil_privacy_incident ADD COLUMN information CLOB DEFAULT NULL;
ALTER TABLE phil_privacy_incident ADD COLUMN undescribed_reason CLOB DEFAULT NULL;
ALTER TABLE phil_privacy_incident ADD COLUMN occurred_until DATETIME DEFAULT NULL;
ALTER TABLE phil_privacy_incident ADD COLUMN occurrence_approximate BOOLEAN DEFAULT 0 NOT NULL;
ALTER TABLE phil_privacy_incident ADD COLUMN discovered_until DATETIME DEFAULT NULL;
ALTER TABLE phil_privacy_incident ADD COLUMN affected_approximate BOOLEAN DEFAULT 0 NOT NULL;
ALTER TABLE phil_privacy_incident ADD COLUMN risk_assessment CLOB DEFAULT NULL;
ALTER TABLE phil_privacy_incident ADD COLUMN public_notice_at DATETIME DEFAULT NULL;
ALTER TABLE phil_privacy_incident ADD COLUMN public_notice_reason VARCHAR(32) DEFAULT NULL;
```

Une nouvelle installation crée le registre et sa table des corrections entiers, avec la table du journal ci-dessus :

MySQL 8.4 et MariaDB :

```sql
CREATE TABLE phil_privacy_incident (id VARCHAR(32) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_bin`, summary LONGTEXT NOT NULL, discovered_at DATETIME NOT NULL, occurred_at DATETIME DEFAULT NULL, categories LONGTEXT NOT NULL, affected INT DEFAULT NULL, serious_risk TINYINT NOT NULL, measures LONGTEXT NOT NULL, authority_notified_at DATETIME DEFAULT NULL, subjects_notified_at DATETIME DEFAULT NULL, closed_at DATETIME DEFAULT NULL, information LONGTEXT DEFAULT NULL, undescribed_reason LONGTEXT DEFAULT NULL, occurred_until DATETIME DEFAULT NULL, occurrence_approximate TINYINT DEFAULT 0 NOT NULL, discovered_until DATETIME DEFAULT NULL, affected_approximate TINYINT DEFAULT 0 NOT NULL, risk_assessment LONGTEXT DEFAULT NULL, public_notice_at DATETIME DEFAULT NULL, public_notice_reason VARCHAR(32) DEFAULT NULL, INDEX idx_phil_privacy_incident_discovered (discovered_at), PRIMARY KEY (id));
CREATE TABLE phil_privacy_incident_revision (incident_id VARCHAR(32) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_bin`, sequence INT NOT NULL, field VARCHAR(32) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_bin`, previous LONGTEXT DEFAULT NULL, revised_at DATETIME NOT NULL, PRIMARY KEY (incident_id, sequence, field));
```

PostgreSQL 17 :

```sql
CREATE TABLE phil_privacy_incident (id VARCHAR(32) NOT NULL, summary TEXT NOT NULL, discovered_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, occurred_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, categories TEXT NOT NULL, affected INT DEFAULT NULL, serious_risk BOOLEAN NOT NULL, measures TEXT NOT NULL, authority_notified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, subjects_notified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, closed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, information TEXT DEFAULT NULL, undescribed_reason TEXT DEFAULT NULL, occurred_until TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, occurrence_approximate BOOLEAN DEFAULT false NOT NULL, discovered_until TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, affected_approximate BOOLEAN DEFAULT false NOT NULL, risk_assessment TEXT DEFAULT NULL, public_notice_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, public_notice_reason VARCHAR(32) DEFAULT NULL, PRIMARY KEY (id));
CREATE INDEX idx_phil_privacy_incident_discovered ON phil_privacy_incident (discovered_at);
CREATE TABLE phil_privacy_incident_revision (incident_id VARCHAR(32) NOT NULL, sequence INT NOT NULL, field VARCHAR(32) NOT NULL, previous TEXT DEFAULT NULL, revised_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (incident_id, sequence, field));
```

Chaque instant est écrit en UTC. Deux corrections de la même ligne sauvegardées au même moment ne partagent jamais un
numéro : la seconde échoue sur la clé primaire de la table des corrections, et se sauvegarde de nouveau. Il en va de même de deux
entrées du journal d'un même incident.

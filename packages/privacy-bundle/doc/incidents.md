# The register of confidentiality incidents

The law asks a business to keep a register of **every** confidentiality incident — personal information accessed,
used, disclosed or lost without authorization —, serious or not, and to notify the authority and the people concerned
of one that presents a risk of serious injury. The bundle keeps that register, with the eight paragraphs Québec asks
of each line, and keeps it at least five years.

## What Québec asks

The *Act respecting the protection of personal information in the private sector* (chapter P-39.1) sets the duties,
and the *Regulation respecting confidentiality incidents* (chapter A-2.1, r. 3.1) the content of the notices and of the
register:

| Text | What it asks | What the bundle does |
|---|---|---|
| P-39.1, s. 3.6 | An incident is an access, use or communication the law does not authorize, a loss, or any other breach of the protection of personal information. | Every such incident is a line, serious or not. |
| P-39.1, s. 3.5, para. 1 | Once it has reason to believe an incident occurred, the business takes reasonable measures to reduce the risk of injury and to prevent a new incident of the same nature. | `--measures`, paragraph 8 of the register. |
| P-39.1, s. 3.5, para. 2 | If the risk of injury is serious, the business promptly notifies the Commission d'accès à l'information and every person concerned. | `pendingNotifications()` names the notices still owed after each record; `--authority-notified` and `--subjects-notified` date them. |
| P-39.1, s. 3.5 | The notice to a person concerned is not given while it could hamper an investigation by a person or a body in charge by law of the prevention, detection or repression of crime. | A `notice_deferral` in the journal of the incident, with the body and the reason, and a `deferral_lifted` once the investigation no longer stands in the way ([below](#the-journal-of-an-incident)). |
| P-39.1, s. 3.5 | The business may communicate personal information concerned by the incident, without consent, to a person or a body that could reduce the risk, giving only what that purpose needs; the person in charge of the protection of personal information records the communication. | A `third_party_communication` in the journal of the incident: when, to whom, which information and why. |
| P-39.1, s. 3.7 | The risk is judged by the sensitivity of the information, the anticipated consequences of its use and the likelihood of its use for injurious purposes, after consulting the person in charge of the protection of personal information. | `--risk-assessment`, paragraph 6 of the register, and an `officer_consultation` in the journal of the incident: when, who was consulted, and what they answered. The judgement and the consultation belong to that person. |
| P-39.1, s. 3.8 | The business keeps a register of incidents, and sends a copy to the Commission at its request. | The table `phil_privacy_incident`; `phil:privacy:incidents` prints the copy. |
| r. 3.1, ss. 3 to 5 | The content of the notice to the Commission, of its follow-ups, and of the notice to each person. | `phil:privacy:incident-notice` writes a draft of each from the register ([incident-notices.md](incident-notices.md)). |
| r. 3.1, s. 6 | A notice to the person becomes a public notice when sending it would cause greater injury, would be excessively difficult, or when the business has no contact details; a public notice may also be given to act quickly, the notice to each person still owed. | `--public-notice` and `--public-notice-reason`; the first three reasons stand for the notice to each person; `phil:privacy:incident-notice --to=public` drafts the public notice only for an incident with a reason. |
| r. 3.1, s. 7 | The eight paragraphs of each line of the register, below. | Each has its fields; `missingParagraphs()` names those a line does not answer yet. |
| r. 3.1, s. 8 | The register is kept up to date and kept at least five years after the date or period the business became aware of the incident. | Lines are completed and corrected with their corrections kept; a shorter retention rule fails the build, and the purge never goes below five years. |

The notices to the Commission and to the people (sections 3 to 6 of the regulation) are judged, reviewed and sent by
the person in charge of privacy; `phil:privacy:incident-notice` writes their drafts from the register, and the bundle
records their dates. See [The drafts of the notices](incident-notices.md). The Commission's guidelines of 30 January
2026 on confidentiality incidents explain how to judge the risk and what the notices say. The GDPR asks the same record
of breaches (article 33(5)) and a notice to the authority within 72 hours. See [legal-framework.md](legal-framework.md).

## Recording and completing a line

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

A line holds, paragraph by paragraph of section 7 of the regulation:

| Paragraph | Field | Option | Rule |
|---|---|---|---|
| 1. The personal information concerned | categories | `--category`, repeated | The kinds of personal information, as `DataCategory` tokens. |
| | information | `--information` | A description of the information, at most 4,000 characters. |
| | undescribed reason | `--undescribed-reason` | Why the information cannot be described, at most 4,000 characters; never with a description. |
| 2. The circumstances | summary | the argument | What happened; required, at most 4,000 characters. |
| 3. The date or period of the incident | occurred | `--occurred` | When it happened, or the start of the period, when known; never after the end of the discovery. |
| | occurred until | `--occurred-until` | The end of the period; never before its start, and only with `--occurred`. |
| | approximate occurrence | `--approximate-occurrence`, `--no-approximate-occurrence` | Whether the date or period is an approximation; only with `--occurred`. |
| 4. The date or period of the discovery | discovered | `--discovered` | When the business became aware of it, or the start of the period; now by default. |
| | discovered until | `--discovered-until` | The end of the period; never before its start. |
| 5. The number of people | affected | `--affected` | How many people, when known. |
| | approximate affected | `--approximate-affected`, `--no-approximate-affected` | Whether the number is an approximation; only with `--affected`. |
| 6. The risk of serious injury | serious risk | `--serious-risk`, `--no-serious-risk` | Whether it presents a risk of serious injury. |
| | risk assessment | `--risk-assessment` | What leads to that conclusion, serious or not, at most 4,000 characters. |
| 7. The notices | authority notified | `--authority-notified` | When the Commission was notified. |
| | people notified | `--subjects-notified` | When the people concerned were notified. |
| | public notice | `--public-notice`, `--public-notice-reason` | When a public notice was given and why: `increased_injury`, `excessive_hardship`, `no_contact_details` or `prompt_action`; one never without the other. |
| 8. The measures | measures | `--measures` | What was done to reduce the risk, at most 4,000 characters. |
| — | closed | `--closed` | When it was closed. |

No notice and no closing can come before the start of the discovery. Paragraphs 2 and 4 are required to record a line;
the others are recorded when they are known, and after each record the command names those still missing
(`Incident::missingParagraphs()`): 1 without a description nor its reason, 3 without a date of the incident, 5 without
a number, 6 without an assessment, 7 while a notice is still owed, 8 without measures. With a risk of serious injury, it
also names the notices still owed (`Incident::pendingNotifications()`); a public notice given for one of the first three
reasons stands for the notice to each person. Whether a risk is serious, and the notices themselves, belong to the person
in charge of privacy.

### Correcting a line

With `--id`, the command completes or corrects a line: a value left out keeps the one it had, the summary given as the
argument replaces the one it had, and `--clear` empties a date or a number recorded by mistake (`occurred`,
`occurred-until`, `discovered-until`, `affected`, `authority-notified`, `subjects-notified`, `public-notice`, `closed`).
A text is emptied by an empty value of its option. Emptying `occurred` also empties the end of the incident and its
approximation; emptying `affected`, its approximation.

A correction never loses what the register said: the table `phil_privacy_incident_revision` keeps, for each field a
save changes, its previous value and the instant of the correction, numbered per incident, in the same transaction
(`IncidentHistory::revisions()`). A line is never removed by hand.

## The journal of an incident

Beside the eight paragraphs, sections 3.5 and 3.7 of P-39.1 leave steps to keep a trace of: a communication to a third
party that could reduce the risk, a notice put off for an investigation, and the consultation of the person in charge of
the protection of personal information. Each is an entry of the journal of the incident, numbered from 1 and only ever
added to:

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

| Kind (`--kind`) | Section | `--party` | `--details` |
|---|---|---|---|
| `third_party_communication` | 3.5 | Who received the information | Required: which information, and how it could reduce the risk |
| `notice_deferral` | 3.5 | The body that investigates | Required: which investigation the notice could hamper; `--until`, its expected end, when known |
| `deferral_lifted` | 3.5 | The body that investigates | Optional |
| `officer_consultation` | 3.7 | The person consulted | Required: what they were asked, and what they answered |

`--at`, when it was done, is now by default, never before the start of the discovery and never ahead of time. `--party`
is at most 255 characters and `--details` at most 4,000. A `deferral_lifted` is refused unless a deferral is in force.
The notices still owed (`pendingNotifications()`) stay owed during a deferral: the journal says why one is not given
yet.

In code, `Application\Incident\IncidentJournalRecorder::record()` writes an entry (`Domain\Incident\JournalEntry`,
of a `JournalKind`), `entries()` reads them, and `isDeferred()` says whether a deferral is in force; a refusal is a
`JournalRefused`. The register keeps the journal when it implements `Application\Contract\IncidentJournal`, as the
table of the bundle does; with another store that does not, the recorder refuses every entry.

### Printing the register

`phil:privacy:incidents` prints the register, the most recently discovered first, to hand to the Commission that asks
for a copy: a Markdown table with a column per field and the paragraphs still missing, then a table of the corrections
and a table of the journal; or JSON with every field, `pending_notifications`, `missing_paragraphs`, `revisions` and
`journal` for each line.

## Keeping it

The register is the table `phil_privacy_incident`, added to the schema the ORM generates; another store is an
`IncidentRegister` named under `phil_privacy.incidents.store`. Keep it with a retention rule on its store, which
removes the closed incidents known since before the cutoff, never an open one, with their corrections and their
journal:

```yaml
phil_privacy:
    retention:
        rules:
            R-INC: { store: phil_privacy_incident, period: P5Y, purpose: The register of confidentiality incidents, location: Canada }
```

Section 8 of the regulation keeps each line at least five years after the date or period of the discovery
(`Incident::MINIMUM_RETENTION`). Two guards hold that floor:

- **at the build**, a fixed period on `phil_privacy_incident` shorter than five years fails the container build,
  citing the section: `P1Y`, `P59M` and `P1826D` are refused, `P5Y`, `P60M` and `P7Y` kept
  (`RetentionPeriod::lastsAtLeast()`);
- **at the purge**, `DbalIncidentRegister` never removes a line known for less than five years, whatever the cutoff it
  is given, such as one from a `retention_schedule_source` read at run time. A line discovered over a period counts
  from the end of the period.

## The tables

`PrivacySchemaListener` adds the columns, the table of corrections and the table of the journal to the schema the ORM
generates, so `doctrine:migrations:diff` writes the migration; on a register already in use, the new columns are
nullable or false by default, and the lines already there are kept. Without the ORM, the table of the journal is:

MySQL 8.4 and MariaDB:

```sql
CREATE TABLE phil_privacy_incident_journal (incident_id VARCHAR(32) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_bin`, sequence INT NOT NULL, kind VARCHAR(32) NOT NULL, done_at DATETIME NOT NULL, party VARCHAR(255) NOT NULL, details LONGTEXT NOT NULL, until_at DATETIME DEFAULT NULL, recorded_at DATETIME NOT NULL, PRIMARY KEY (incident_id, sequence));
```

PostgreSQL 17:

```sql
CREATE TABLE phil_privacy_incident_journal (incident_id VARCHAR(32) NOT NULL, sequence INT NOT NULL, kind VARCHAR(32) NOT NULL, done_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, party VARCHAR(255) NOT NULL, details TEXT NOT NULL, until_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, recorded_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (incident_id, sequence));
```

SQLite:

```sql
CREATE TABLE phil_privacy_incident_journal (incident_id VARCHAR(32) NOT NULL, sequence INTEGER NOT NULL, kind VARCHAR(32) NOT NULL, done_at DATETIME NOT NULL, party VARCHAR(255) NOT NULL, details CLOB NOT NULL, until_at DATETIME DEFAULT NULL, recorded_at DATETIME NOT NULL, PRIMARY KEY (incident_id, sequence));
```

The migration of a register in use, written before the paragraphs of section 7, is:

MySQL 8.4 and MariaDB:

```sql
CREATE TABLE phil_privacy_incident_revision (incident_id VARCHAR(32) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_bin`, sequence INT NOT NULL, field VARCHAR(32) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_bin`, previous LONGTEXT DEFAULT NULL, revised_at DATETIME NOT NULL, PRIMARY KEY (incident_id, sequence, field));
ALTER TABLE phil_privacy_incident ADD information LONGTEXT DEFAULT NULL, ADD undescribed_reason LONGTEXT DEFAULT NULL, ADD occurred_until DATETIME DEFAULT NULL, ADD occurrence_approximate TINYINT DEFAULT 0 NOT NULL, ADD discovered_until DATETIME DEFAULT NULL, ADD affected_approximate TINYINT DEFAULT 0 NOT NULL, ADD risk_assessment LONGTEXT DEFAULT NULL, ADD public_notice_at DATETIME DEFAULT NULL, ADD public_notice_reason VARCHAR(32) DEFAULT NULL;
```

PostgreSQL 17:

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

SQLite:

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

A new install creates the register and its table of corrections whole, with the table of the journal above:

MySQL 8.4 and MariaDB:

```sql
CREATE TABLE phil_privacy_incident (id VARCHAR(32) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_bin`, summary LONGTEXT NOT NULL, discovered_at DATETIME NOT NULL, occurred_at DATETIME DEFAULT NULL, categories LONGTEXT NOT NULL, affected INT DEFAULT NULL, serious_risk TINYINT NOT NULL, measures LONGTEXT NOT NULL, authority_notified_at DATETIME DEFAULT NULL, subjects_notified_at DATETIME DEFAULT NULL, closed_at DATETIME DEFAULT NULL, information LONGTEXT DEFAULT NULL, undescribed_reason LONGTEXT DEFAULT NULL, occurred_until DATETIME DEFAULT NULL, occurrence_approximate TINYINT DEFAULT 0 NOT NULL, discovered_until DATETIME DEFAULT NULL, affected_approximate TINYINT DEFAULT 0 NOT NULL, risk_assessment LONGTEXT DEFAULT NULL, public_notice_at DATETIME DEFAULT NULL, public_notice_reason VARCHAR(32) DEFAULT NULL, INDEX idx_phil_privacy_incident_discovered (discovered_at), PRIMARY KEY (id));
CREATE TABLE phil_privacy_incident_revision (incident_id VARCHAR(32) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_bin`, sequence INT NOT NULL, field VARCHAR(32) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_bin`, previous LONGTEXT DEFAULT NULL, revised_at DATETIME NOT NULL, PRIMARY KEY (incident_id, sequence, field));
```

PostgreSQL 17:

```sql
CREATE TABLE phil_privacy_incident (id VARCHAR(32) NOT NULL, summary TEXT NOT NULL, discovered_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, occurred_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, categories TEXT NOT NULL, affected INT DEFAULT NULL, serious_risk BOOLEAN NOT NULL, measures TEXT NOT NULL, authority_notified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, subjects_notified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, closed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, information TEXT DEFAULT NULL, undescribed_reason TEXT DEFAULT NULL, occurred_until TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, occurrence_approximate BOOLEAN DEFAULT false NOT NULL, discovered_until TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, affected_approximate BOOLEAN DEFAULT false NOT NULL, risk_assessment TEXT DEFAULT NULL, public_notice_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, public_notice_reason VARCHAR(32) DEFAULT NULL, PRIMARY KEY (id));
CREATE INDEX idx_phil_privacy_incident_discovered ON phil_privacy_incident (discovered_at);
CREATE TABLE phil_privacy_incident_revision (incident_id VARCHAR(32) NOT NULL, sequence INT NOT NULL, field VARCHAR(32) NOT NULL, previous TEXT DEFAULT NULL, revised_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (incident_id, sequence, field));
```

Every instant is written in UTC. Two corrections of the same line saved at the same moment never share a number: the
second fails on the primary key of the table of corrections, and is saved again. The same holds for two entries of the
journal of one incident.

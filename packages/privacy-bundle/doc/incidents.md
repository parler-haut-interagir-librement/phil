# The register of confidentiality incidents

The law asks a business to keep a register of **every** confidentiality incident — personal information accessed,
used, disclosed or lost without authorization —, serious or not, and to notify the authority and the people concerned
of one that presents a risk of serious injury. The bundle keeps that register.

In Québec, the Commission d'accès à l'information sets out what the register holds, how to judge whether an incident
presents a risk of serious injury, and what the notices say, in its guidelines of 30 January 2026 on confidentiality
incidents; read them with the person in charge of privacy. The GDPR asks the same record of breaches (article 33(5))
and a notice to the authority within 72 hours. See [legal-framework.md](legal-framework.md).

```bash
bin/console phil:privacy:incident "Mailing list exported to a public folder" \
    --occurred="2026-11-09 08:00" --category=email --category=person_name --affected=120 --serious-risk
# Incident 4f3c… recorded.
# It presents a risk of serious injury: notify the authority promptly, then record it with --authority-notified.
# It presents a risk of serious injury: notify the people concerned promptly, then record it with --subjects-notified.

bin/console phil:privacy:incident --id=4f3c… --measures="Folder removed, link revoked" --authority-notified="2026-11-10 15:00"
bin/console phil:privacy:incident --id=4f3c… --subjects-notified=2026-11-11 --closed=2026-11-20

bin/console phil:privacy:incidents --format=markdown > incidents.md
```

A line holds:

| Field | Option | Rule |
|---|---|---|
| summary | the argument | What happened; required for a new incident, at most 4,000 characters. |
| discovered | `--discovered` | When it was discovered; now by default. |
| occurred | `--occurred` | When it happened, when known; never after the discovery. |
| categories | `--category`, repeated | The kinds of personal information concerned, as `DataCategory` tokens. |
| affected | `--affected` | How many people, when known. |
| serious risk | `--serious-risk`, `--no-serious-risk` | Whether it presents a risk of serious injury. |
| measures | `--measures` | What was done to reduce the risk and prevent another, at most 4,000 characters. |
| authority notified | `--authority-notified` | When the authority was told. |
| people notified | `--subjects-notified` | When the people concerned were told. |
| closed | `--closed` | When it was closed. |

No date can come before the discovery. With `--id`, the command completes a line: a value left out keeps the one it
had. After each record it names the notifications a risk of serious injury still asks for
(`Incident::pendingNotifications()`). Whether a risk is serious, and the notices themselves, belong to the person in
charge of privacy.

`phil:privacy:incidents` prints the register, the most recently discovered first, as a Markdown table to hand to the
authority that asks for it, or as JSON with `pending_notifications` for each line.

## Keeping it

The register is the table `phil_privacy_incident`, added to the schema the ORM generates; another store is an
`IncidentRegister` named under `phil_privacy.incidents.store`. A line is never removed by hand. Keep it at least as long
as the law asks — five years from the discovery in Québec — with a retention rule on its store, which removes the closed
incidents discovered before the cutoff, never an open one:

```yaml
phil_privacy:
    retention:
        rules:
            R-INC: { store: phil_privacy_incident, period: P5Y, purpose: The register of confidentiality incidents, location: Canada }
```

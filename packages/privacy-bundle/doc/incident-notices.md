# The drafts of the notices of an incident

When a confidentiality incident presents a risk of serious injury, the business notifies the Commission d'accès à
l'information and the people concerned. `phil:privacy:incident-notice` writes a **draft** of each notice in Markdown,
filled from the register of incidents and from the identity of the organisation. The bundle never sends anything: the
person in charge of the protection of personal information judges whether a notice is owed, reviews the draft,
completes it and sends it.

This page explains what the bundle does with the regulation; it is not legal advice.

## What the regulation asks of the notices

The *Regulation respecting confidentiality incidents* (chapter A-2.1, r. 3.1) sets what each notice contains:

| Section | What it asks | Draft |
|---|---|---|
| s. 3 | The written notice to the Commission, in eleven paragraphs, listed below. | `--to=commission` |
| s. 4 | What the business learns after its notice, among those paragraphs, is sent to the Commission promptly. | `--to=commission --follow-up` |
| s. 5 | The notice to each person concerned, in six paragraphs, listed below. | `--to=subject` |
| s. 6 | The notice goes to each person. It becomes a public notice when sending it would cause the person greater injury, would be excessively difficult, or when the business has no contact details for the people. A public notice may also be given to act quickly; the notice to each person is then still owed, except in those three cases. | `--to=public` |

The duty to notify comes from section 3.5 of the *Act respecting the protection of personal information in the private
sector* (chapter P-39.1); the regulation applies to public bodies, businesses, professional orders and political
parties alike. See [The register of incidents](incidents.md) for the register itself.

## Who decides, who sends

| Step | Who |
|---|---|
| Whether the risk of injury is serious (P-39.1, s. 3.7), after consulting the person in charge of the protection of personal information | The business |
| What the notice says, and the rubrics the register cannot fill | The person in charge |
| Sending the notice, by writing to the Commission, to each person, or in public | The person in charge |
| Recording the date of the notice in the register | The person in charge, with `phil:privacy:incident` |
| Writing a draft from what the register holds | `phil:privacy:incident-notice` |

The command calls no mailer, no notifier, no message bus and no HTTP client, writes nothing to the register, and never
logs the content of a draft.

## Writing a draft

```bash
bin/console phil:privacy:incident-notice 4f3c… --to=commission --locale=fr --output=avis-commission.md
bin/console phil:privacy:incident-notice 4f3c… --to=subject --locale=en
bin/console phil:privacy:incident-notice 4f3c… --to=public
bin/console phil:privacy:incident-notice 4f3c… --to=commission --follow-up
bin/console phil:privacy:incident-notice 4f3c… --to=commission --follow-up --since="2026-11-14 10:00"
```

| Option | Rule |
|---|---|
| the argument | The identifier of the incident in the register. |
| `--to` | `commission`, `subject` or `public`; required. |
| `--locale` | `fr` (by default) or `en`, the language of the draft. |
| `--output` | A new file to write the draft to, readable by its owner only; a file that exists is refused. Without it, the draft goes to the standard output. |
| `--follow-up` | With `--to=commission`, the follow-up of section 4. |
| `--since` | With `--follow-up`, the date of the last notice or follow-up sent; the date of the notice to the Commission recorded in the register by default. |

The draft goes alone to the standard output, so that `> notice.md` keeps only the draft. The rubrics left to complete,
the reminders and the next step go to the error output, or to the standard output after a draft written with
`--output`:

```text
To complete in the draft: s. 3, para. 4 (cause); s. 3, para. 7 (affected in quebec); s. 3, para. 11 (other authority).
Nothing was sent. Review the draft, send it to the Commission in writing, then record the date: phil:privacy:incident --id=4f3c… --authority-notified=<date>.
```

Dates are written in the time zone of the application's clock, without the time when it is midnight.

## The rubrics and where they come from

Each rubric the register or the organisation knows is filled. A rubric they do not know stays in its place in the
draft, marked in bold, `**To complete: the cause of the incident, if known (s. 3, para. 4)**`, and the command lists it
after the draft, the way `phil:privacy:incident` lists the paragraphs of the register still missing.

### The notice to the Commission (section 3)

| Para. | Rubric | From |
|---|---|---|
| 1 | The name of the organisation and, if it has one, its Québec enterprise number (NEQ) | The organisation, below |
| 2 | The name and contact information of the person to contact about the incident | The organisation, below |
| 3 | The personal information concerned, or the reason it cannot be described | `categories`, `information`, `undescribedReason` |
| 4 | A brief description of the circumstances | `summary` |
| 4 | The cause, if known | Always to complete |
| 5 | The date or period of the incident, or an approximation | `occurredAt`, `occurredUntil`, `occurrenceApproximate` |
| 6 | The date or period the organisation became aware of it | `discoveredAt`, `discoveredUntil` |
| 7 | The number of people concerned, or an approximation | `affected`, `affectedApproximate` |
| 7 | How many of them reside in Québec, or an approximation | Always to complete |
| 8 | What leads to conclude that there is a risk of serious injury | `riskAssessment` |
| 9 | The measures taken or planned to notify the people, with the date or the time expected | `subjectsNotifiedAt`, `publicNoticeAt` and its reason; to complete while neither is recorded |
| 10 | The measures taken or planned to reduce the risk and prevent a new incident, with their dates | `measures`; write their dates in it |
| 11 | That an authority outside Québec with similar functions was notified, if one was | Always to complete |

### The notice to each person, or in public (sections 5 and 6)

| Para. | Rubric | From |
|---|---|---|
| 1 | The personal information concerned, or the reason it cannot be described | `categories`, `information`, `undescribedReason` |
| 2 | A brief description of the circumstances | `summary` |
| 3 | The date or period of the incident, or an approximation | `occurredAt`, `occurredUntil`, `occurrenceApproximate` |
| 4 | The measures taken or planned to reduce the risk of injury | `measures` |
| 5 | The measures the organisation suggests to the person to reduce the risk or mitigate the injury | Always to complete |
| 6 | Whom to contact to learn more | The organisation, below |

The drafts for the people speak to them in plain headings (*What happened*, *When*, *What we suggest you do*…); an HTML
comment under each heading names its paragraph, and the incident's identifier, so the draft stays traceable without
showing it to the reader.

### What the register does not keep

Four rubrics of section 3 and one of section 5 have no field in the register: the cause, the number of people
residing in Québec, an authority outside Québec, and the measures suggested to the people, together with the plans
and dates of paragraphs 9 and 10 beyond what `measures` and the dates of the notices say. Section 7 does not ask the
register for them; they are facts the person in charge states in the notice itself. The draft always marks them to
complete, rather than adding columns to a register that does not need them.

### Who gives the notice

The organisation comes from a `NoticeOrganisationSource`. With phil/legal-notices-bundle installed, the bundle reads
the identity of the site:

| Rubric | Field of the identity |
|---|---|
| The name of the organisation | `publisher_name` |
| The Québec enterprise number | `publisher_registration`; a Québec business puts its NEQ there |
| The person to contact | `dpo_name` |
| How to reach them | `dpo_contact` |

An application names its own source by aliasing `Phil\PrivacyBundle\Application\Contract\NoticeOrganisationSource` to
a service of its own; without either, these rubrics are left to complete. See
[extension-points.md](extension-points.md#noticeorganisationsource).

## The follow-up to the Commission (section 4)

`--follow-up` lists the rubrics of section 3 a correction changed after the last notice: it reads the corrections the
register keeps (`IncidentHistory`, the table `phil_privacy_incident_revision`) and keeps those recorded after the date
of the notice to the Commission, `authorityNotifiedAt`, or after `--since`. A changed paragraph is repeated whole, and
the organisation and the person to contact head the draft. When nothing changed, the command says so and writes no
draft; without a recorded notice and without `--since`, or with a register that keeps no corrections, it refuses.

The register keeps one date of notice to the Commission. Keep the date of each follow-up sent and give it to `--since`
for the next one.

## The public notice (section 6)

`--to=public` is refused for an incident without a public notice reason, with a message that names the three cases of
section 6. Record the reason first:

```bash
bin/console phil:privacy:incident --id=4f3c… --public-notice=2026-11-12 --public-notice-reason=no_contact_details
```

The reason is written as an HTML comment at the head of the draft. With `increased_injury`, `excessive_hardship` or
`no_contact_details`, the public notice takes the place of the notice to each person; with `prompt_action`, the command
reminds that the notice to each person is still owed promptly. A draft for each person reminds the three cases in
which a public notice replaces it.

## After sending

The command records nothing. Once the notice is sent, record its date:

```bash
bin/console phil:privacy:incident --id=4f3c… --authority-notified="2026-11-11 10:30"
bin/console phil:privacy:incident --id=4f3c… --subjects-notified=2026-11-12
```

## Changing the drafts

The drafts are Twig templates rendered as Markdown, in `@PhilPrivacy/incident_notice/`: `commission.md.twig`,
`follow_up.md.twig`, `subject.md.twig`, `public.md.twig`, the rubrics shared in `_commission_rubrics.md.twig` and
`_people_rubrics.md.twig`, and the pieces in `_macros.md.twig`. An application overrides one in
`templates/bundles/PhilPrivacy/incident_notice/`. Their texts are in the translation domain `phil_privacy`, under
`incident_notice.`, in French and in English; override a text in the application's `translations/` directory.

A template receives:

| Variable | What it holds |
|---|---|
| `incident` | The `Incident`. |
| `organisation` | The `NoticeOrganisation`: `name`, `enterpriseNumber`, `contactName`, `contactDetails`, null when not known. |
| `locale` | `fr` or `en`. |
| `rubric` | For each rubric by name, its `section`, `paragraph`, whether it is `shown` and whether it is `missing`. |
| `dates` | `discovered`, `discovered_until`, `occurred`, `occurred_until`, `subjects_notified`, `public_notice` and `since`, written in the clock's time zone, or null. |

The command needs Twig and the translator, besides the register and the clock; without them it is not registered.

## Not covered

- The notice to the holder of parental authority for a person under fourteen, which the regulation does not address.
- The communication to a person or body that can reduce the risk (P-39.1, s. 3.5, para. 2), the notice deferred for an
  investigation (para. 3), and the record of the consultation of the person in charge (s. 3.7).

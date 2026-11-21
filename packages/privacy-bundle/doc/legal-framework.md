# Legal framework

This page names the rules the bundle is built around, and says what its default configuration does about each. It is
not legal advice: the person in charge of privacy decides what a site owes, and the texts themselves prevail over this
summary.

## What applies today

### Cookies and similar trackers: the ePrivacy Directive and the CNIL

Article 5(3) of the ePrivacy Directive (2002/58/EC) makes storing information on a person's device, or reading it,
subject to their prior consent, unless it is strictly necessary for the service they asked for. The CNIL, the French
authority, applies it through its guidelines and recommendation on cookies and other trackers: consent before any
optional tracker, refusing as easy as accepting, a choice that can be withdrawn at any time, and proof of it.

| The rule | What the bundle does by default |
|---|---|
| Nothing optional before consent | Every optional category is refused until the visitor grants it ([consent.md](consent.md)). |
| Refusing as easy as accepting | The banner shows « Refuse all » and « Accept all » side by side, the same element and the same style. |
| Withdrawal at any time | The page of the choices is always reachable, and works without JavaScript. |
| Proof of consent | Each choice leaves a dated proof of the version of the text, naming no one. |
| Asking again after a while | A choice is kept six months (`consent.lifetime: P6M`), then the visitor is asked again. |
| Third-party content | A frame of another site waits behind a preview served by the site ([third-party-frames.md](third-party-frames.md)). |
| A list of every tracker | The cookie inventory, the banner and the cookie policy drawn from it, and a test that fails on an undeclared cookie ([cookie-inventory.md](cookie-inventory.md)). |

The bundle also honours the Global Privacy Control: a browser that sends `Sec-GPC: 1` is refused every optional
category without being asked.

A pixel in an email reads from the recipient's device too. The bundle tracks no email, and its per-member consent
([email-tracking.md](email-tracking.md)) is off until the application turns it on, each purpose then asked on its own.

### Québec: Law 25

The *Act respecting the protection of personal information in the private sector* (chapter P-39.1), as amended by
Law 25, applies to businesses that hold personal information about people in Québec; its regulations, such as the
*Regulation respecting confidentiality incidents* (chapter A-2.1, r. 3.1), apply to the same businesses.

| The rule | What the bundle does by default |
|---|---|
| The highest level of confidentiality by default | Optional cookies refused, email tracking off, nothing public without the person's choice. |
| Technology that identifies, locates or profiles a person is off until they turn it on | No optional tracker runs before consent. |
| Access, rectification and portability | A member downloads their data from their preferences, as a ZIP archive with a JSON file per module ([preferences.md](preferences.md)). |
| Destroying information once its purpose is served | Retention rules, purged by a scheduled job ([retention.md](retention.md)). |
| A register of every confidentiality incident, and notices when a risk of serious injury exists (sections 3.5 to 3.8 of the Act) | The register and its commands, which name the notices still owed ([incidents.md](incidents.md)), and drafts of the notices the person in charge reviews and sends ([incident-notices.md](incident-notices.md)). |
| The eight paragraphs of each line of that register, kept up to date and kept at least five years after the discovery (*Regulation respecting confidentiality incidents*, chapter A-2.1, r. 3.1, sections 7 and 8) | A field for each paragraph, the paragraphs still missing named after each record, corrections kept with their previous values, a retention rule under five years refused at the build and a purge that never goes below five years ([incidents.md](incidents.md#what-québec-asks)). |
| Knowing what is held and where | The register of processing, drawn from the retention schedule and the declared fields ([export-and-erasure.md](export-and-erasure.md#the-register-of-processing)). |

### The European Union: the GDPR

| The rule | What the bundle does by default |
|---|---|
| Consent freely given, specific, informed and withdrawable (articles 4 and 7) | One choice per category or purpose, refused by default, withdrawn with the same form. |
| Right of access and to portability (articles 15 and 20) | The member's export, on by default; the request of a person without an account, once turned on ([requests-without-an-account.md](requests-without-an-account.md)). |
| Right to erasure (article 17) | The account deletion, on by default, with a delay during which it can be cancelled ([account-deletion.md](account-deletion.md)). |
| Records of processing activities (article 30) | `phil:privacy:register`, and its `--strict` mode for continuous integration. |
| Storage limitation (article 5(1)(e)) | Retention rules and their purges. |
| Personal data breaches (articles 33 and 34) | The register of incidents; the notice to the authority is due within 72 hours of becoming aware of a breach that presents a risk. |
| Data protection by design and by default (article 25) | Log redaction before a line is written, anonymized copies for development ([log-redaction.md](log-redaction.md), [anonymization.md](anonymization.md)). |

Turning a right off takes the full sentence of [preferences.md](preferences.md#turning-a-right-off) and warns at every
build and before every command: the law of most places requires both.

## What is proposed: the Digital Omnibus

In November 2025 the European Commission proposed a *Digital Omnibus*, a set of amendments to the GDPR and to the
ePrivacy rules on trackers. It is a proposal, not law: its text may change before it is adopted, and nothing in the
bundle depends on it. Among its proposals are moving the rules on reading and storing information on a device into the
GDPR, exempting a few low-risk purposes such as aggregated audience measurement from consent, refusing in one click,
not asking again for some time after a refusal, and honouring a choice expressed once through the browser in a
machine-readable way. The defaults of the bundle — refusal by default, refusal in one click, the Global Privacy
Control — already go in that direction.

## What the configuration leaves to the site

- The categories of cookies and their texts, and the version of each text.
- The retention periods, the purposes, the recipients and the processors of the register.
- Whether a person without an account may ask by email, and whether emails are tracked at all.
- Whether a risk is serious, and the notices themselves.

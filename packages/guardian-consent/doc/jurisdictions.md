# Jurisdictions

## The rules of a jurisdiction

A `Jurisdiction` holds what the library needs of a place's law on the consent of minors:

| Rule | Bounds | Role |
|---|---|---|
| `code` | An ISO 3166 code: a country, `FR`, or a subdivision, `CA-QC` | Names the jurisdiction on a minor's record |
| `consentAge` | 1 to the age of majority | The age from which a person consents for themselves |
| `majorityAge` | Up to 21 | The age of majority, for the `Adult` bracket |
| `timeZone` | A `DateTimeZone` | The zone in which a birthday begins |
| `leapDayRule` | `FirstOfMarch` by default, or `TwentyEighthOfFebruary` | The day a person born on 29 February has a birthday in a common year |

A birthday begins at midnight in the jurisdiction's time zone: at 03:30 UTC on a member's birthday, a member in Quebec
is still a day short of it, a member in France already has it.

## The default jurisdiction

`Jurisdiction::quebec()` — code `CA-QC`, age of consent 14, majority 18, zone `America/Toronto`, birthday of
29 February on 1 March — is the jurisdiction a `Jurisdictions` holds when it is given none. Quebec's
[Act respecting the protection of personal information in the private sector](https://www.legisquebec.gouv.qc.ca/en/document/cs/P-39.1)
asks the consent of the holder of parental authority or the tutor for a minor under 14 (sections 4.1 and 14); from 14,
the minor consents for themselves (section 14).

## Other jurisdictions

The library ships no other table of ages: they change with the law, and the application that serves a place reads its
law. A `Jurisdictions` holds the default jurisdiction and the others served:

```php
use Phil\GuardianConsent\Jurisdiction;
use Phil\GuardianConsent\Jurisdictions;

$jurisdictions = new Jurisdictions(
    Jurisdiction::quebec(),
    new Jurisdiction('FR', 15, 18, new DateTimeZone('Europe/Paris')),
    new Jurisdiction('DE', 16, 18, new DateTimeZone('Europe/Berlin')),
);

$consent->register($member, $birthDate, 'FR');
```

Under the [General Data Protection Regulation](https://eur-lex.europa.eu/eli/reg/2016/679/oj), Article 8 sets the age
from which a child consents to an information society service at 16, and lets each member state set a lower one, not
below 13: configure the age the member state sets. A jurisdiction is served by one `Jurisdictions` once: a code given
twice is refused, and at most 300 are served. A minor's record names its jurisdiction by code; a code removed from the
configuration while records still name it throws `UnknownJurisdiction` when they are read.

## A birthday on 29 February

A person born on 29 February completes a year, in a common year, on 1 March under `FirstOfMarch` and on 28 February
under `TwentyEighthOfFebruary`. In a leap year, the birthday is 29 February under both. Set the rule the
jurisdiction's law gives.

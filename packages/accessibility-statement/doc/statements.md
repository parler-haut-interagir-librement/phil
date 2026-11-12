# Statements

A `Statement` is one version of an accessibility statement. It is never changed once published: the next version is a
new `Statement`, numbered one more.

```php
use Phil\AccessibilityCheck\ConformanceLevel;
use Phil\AccessibilityCheck\SuccessCriterion;
use Phil\AccessibilityStatement\ConformanceStatus;
use Phil\AccessibilityStatement\ConformanceTarget;
use Phil\AccessibilityStatement\Enforcement;
use Phil\AccessibilityStatement\Evaluation;
use Phil\AccessibilityStatement\EvaluationMethod;
use Phil\AccessibilityStatement\FeedbackContact;
use Phil\AccessibilityStatement\LocalizedText;
use Phil\AccessibilityStatement\NonConformity;
use Phil\AccessibilityStatement\NonConformityReason;
use Phil\AccessibilityStatement\Standard;
use Phil\AccessibilityStatement\Statement;

$statement = new Statement(
    version: 1,
    publishedAt: new DateTimeImmutable('2026-11-08'),
    target: new ConformanceTarget(Standard::Wcag22, ConformanceLevel::AA),
    status: ConformanceStatus::Partial,
    feedback: new FeedbackContact(email: 'accessibility@example.com', responseDays: 10),
    nonConformities: [
        new NonConformity(
            SuccessCriterion::CaptionsPrerecorded,
            NonConformityReason::NonCompliance,
            description: new LocalizedText(['en' => 'Videos have no captions yet.', 'fr' => 'Les vidéos n’ont pas encore de sous-titres.']),
            alternative: LocalizedText::of('A transcript is sent on request.'),
            fixBy: new DateTimeImmutable('2027-03-31'),
        ),
    ],
    alternatives: [LocalizedText::of('Call us: we read any page to you.')],
    scope: LocalizedText::of('the site example.com'),
    enforcement: new Enforcement(LocalizedText::of('If our answer does not satisfy you, you may complain to the authority.'), url: 'https://example.org/complaints'),
    evaluation: new Evaluation(new DateTimeImmutable('2026-11-01'), EvaluationMethod::SelfAssessment, pages: 24),
    entity: 'Example Org',
);
```

## The parts

| Part | Type | What it says |
|---|---|---|
| `version` | `int` | The number of the version, from 1. |
| `publishedAt` | `DateTimeImmutable` | When the version was published. |
| `target` | `ConformanceTarget` | The version of WCAG and the level claimed: `WCAG 2.2 AA` by default. |
| `status` | `ConformanceStatus` | `Full`, `Partial` or `NonConformant`. |
| `feedback` | `FeedbackContact` | How a reader reports a barrier: an email, a telephone, a page, a postal address — at least one —, and the days within which an answer comes (1 to 365). |
| `nonConformities` | `list<NonConformity>` | The success criteria not met, at most 1 000. |
| `alternatives` | `list<LocalizedText>` | The ways to reach the content that do not depend on it being accessible, at most 50. |
| `scope` | `?LocalizedText` | What the statement covers: a site, an application, a part of either. |
| `enforcement` | `?Enforcement` | What a reader can do when the answer does not satisfy them: the procedure, the authority, its page. |
| `evaluation` | `?Evaluation` | The last evaluation: when, how (`SelfAssessment`, `ThirdParty`, `Automated`), how many pages, its report. |
| `entity` | `?string` | The organisation that makes the statement, in 1 to 200 characters. |

## The conformance target

`ConformanceTarget` pairs a `Standard` — `Wcag20`, `Wcag21` or `Wcag22` — with a `ConformanceLevel` of
`phil/accessibility-check`. `covers()` says whether a success criterion is measured: it belongs to that version of WCAG
and its level is the target's or lower. `criteria()` lists them in the order of their numbers: 55 for WCAG 2.2 AA, 49
for WCAG 2.1 AA, 37 for WCAG 2.0 AA. `describe()` writes `WCAG 2.2 AA`, and `Standard::url()` gives the
recommendation of the W3C.

## Non-conformities

A `NonConformity` names a `SuccessCriterion` and a `NonConformityReason`:

| Reason | Means | Counts against conformance |
|---|---|---|
| `NonCompliance` | The content does not meet the criterion yet. | yes |
| `DisproportionateBurden` | Meeting it would be a burden out of proportion to its benefit, assessed and stated. | yes |
| `OutOfScope` | The content concerned lies outside the scope of the statement, such as archives or third-party content. | no |

It may add a `description` of what fails and where — the title of the criterion speaks for it otherwise —, the
`alternative` offered meanwhile, the date it will be met (`fixBy`), the number of times an audit met it
(`occurrences`) and whether the last audit found it (`fromAudit`).

`Statement::blocking()` lists those that count against conformance, `withReason()` those of one reason, and
`NonConformity::failedCriteria()` the criteria they fail, once each, in the order of their numbers.

## What a statement refuses

The constructor refuses, with an `InvalidStatement` whose `reason` says why:

- a version below 1 (`version`), too many non-conformities or alternatives (`too_many`);
- a non-conformity whose criterion the target does not measure (`criterion_outside_target`);
- a status the non-conformities contradict (`status`): `Full` while one counts against conformance, `Partial` or
  `NonConformant` while none does;
- an evaluation later than the publication (`evaluation_date`);
- a blank or too long organisation name (`entity`).

Its parts refuse their own bad values the same way: a text in no language or in a tag that is not one, a feedback
contact without any channel, an email PHP's filter refuses, a telephone that is not one, an address that is neither
`https://` nor a path of the site.

## The status the non-conformities support

`ConformanceStatus::derive($target, $nonConformities)` gives `Full` when none counts against conformance,
`NonConformant` when more than half of the criteria of the target fail, and `Partial` otherwise. The generator uses it;
an application writing a statement by hand may claim either `Partial` or `NonConformant` once a criterion fails.

## Texts and addresses

`LocalizedText` holds a plain text per language tag, up to 32 languages and 10 000 characters each; `in($locale,
$fallback)` returns the text of the locale, else of its primary language (`fr` for `fr-CA`), else of the fallback, else
the first. The texts are never HTML: a page escapes them.

An address — of the feedback page, of the report, of the enforcement authority — is checked by `Address::check()`: an
`https://` address, or, except for the authority, a path of the site starting with one `/`. `javascript:`, `http:`,
`//host` and control characters are refused.

## The titles of the criteria in French

`CriterionTitles::french()` gives the title of each success criterion of WCAG 2.2, by number, as the W3C Authorized
Translation into French publishes it, from `data/wcag-2.2-success-criteria.fr.json`. The titles are copied, not
written: `data/NOTICE.md` names the translation, its copyright and the W3C Document License it is used under, whose
notice goes with every copy.

```php
use Phil\AccessibilityStatement\CriterionTitles;

CriterionTitles::french()['1.4.3'];     // 'Contraste (minimum)'
```
